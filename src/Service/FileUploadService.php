<?php

namespace App\Service;

use App\Entity\MessageAttachment;
use App\Entity\Messages;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

class FileUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private SluggerInterface $slugger,
        private LoggerInterface $logger,
        private TimeService $timeService,
        private string $uploadDirectory
    ) {
    }

    /**
     * Validates an uploaded file
     */
    public function validateFile(UploadedFile $file): array
    {
        $errors = [];

        // Check if file was uploaded successfully first
        if (!$file->isValid()) {
            $uploadError = $file->getError();
            switch ($uploadError) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'Le fichier est trop volumineux.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'Le fichier n\'a été que partiellement téléchargé.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = 'Aucun fichier n\'a été téléchargé.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errors[] = 'Dossier temporaire manquant.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errors[] = 'Échec de l\'écriture du fichier sur le disque.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errors[] = 'Une extension PHP a arrêté le téléchargement du fichier.';
                    break;
                default:
                    $errors[] = 'Erreur inconnue lors du téléchargement.';
            }
            
            $this->logger->error('File upload validation failed', [
                'upload_error_code' => $uploadError,
                'filename' => $file->getClientOriginalName(),
                'temp_path' => $file->getPathname()
            ]);
        }

        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $errors[] = sprintf('Le fichier est trop volumineux. Taille maximale autorisée: %s MB', self::MAX_FILE_SIZE / 1024 / 1024);
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            $errors[] = 'Type de fichier non autorisé. Types acceptés: images (JPEG, PNG, GIF, WebP), PDF, documents Word, fichiers texte.';
        }

        return $errors;
    }

    /**
     * Uploads a file and creates a MessageAttachment entity
     */
    public function uploadFile(UploadedFile $file, Messages $message): MessageAttachment
    {
        // Log file details for debugging
        $this->logger->info('Starting file upload', [
            'original_filename' => $file->getClientOriginalName(),
            'temp_path' => $file->getPathname(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'is_valid' => $file->isValid(),
            'error' => $file->getError()
        ]);

        // Check if temp file exists and is readable
        if (!file_exists($file->getPathname())) {
            $this->logger->error('Temporary file does not exist', [
                'temp_path' => $file->getPathname(),
                'original_filename' => $file->getClientOriginalName()
            ]);
            throw new \RuntimeException('Le fichier temporaire n\'existe plus.');
        }

        if (!is_readable($file->getPathname())) {
            $this->logger->error('Temporary file is not readable', [
                'temp_path' => $file->getPathname(),
                'original_filename' => $file->getClientOriginalName()
            ]);
            throw new \RuntimeException('Le fichier temporaire n\'est pas lisible.');
        }

        // Validate file first
        $errors = $this->validateFile($file);
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        // Store file information BEFORE moving the file (UploadedFile becomes invalid after move)
        $clientOriginalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        $originalFilename = pathinfo($clientOriginalName, PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $file->guessExtension();
        $fileName = $safeFilename . '-' . uniqid() . '.' . $extension;

        // Create year/month directory structure
        $now = $this->timeService->now();
        $subDirectory = $now->format('Y/m');
        $targetDirectory = $this->uploadDirectory . '/messages/' . $subDirectory;

        $this->logger->info('Preparing upload directory', [
            'target_directory' => $targetDirectory,
            'filename' => $fileName,
            'sub_directory' => $subDirectory
        ]);

        // Ensure directory exists
        if (!is_dir($targetDirectory)) {
            if (!mkdir($targetDirectory, 0755, true)) {
                $this->logger->error('Failed to create upload directory', [
                    'target_directory' => $targetDirectory
                ]);
                throw new \RuntimeException('Impossible de créer le répertoire de téléchargement.');
            }
        }

        // Check if directory is writable
        if (!is_writable($targetDirectory)) {
            $this->logger->error('Upload directory is not writable', [
                'target_directory' => $targetDirectory
            ]);
            throw new \RuntimeException('Le répertoire de téléchargement n\'est pas accessible en écriture.');
        }

        try {
            $this->logger->info('Moving file', [
                'from' => $file->getPathname(),
                'to' => $targetDirectory . '/' . $fileName
            ]);
            
            // Try to move the file normally first
            $file->move($targetDirectory, $fileName);
            
            $this->logger->info('File moved successfully', [
                'final_path' => $targetDirectory . '/' . $fileName
            ]);
        } catch (FileException $e) {
            $this->logger->error('Failed to move file, trying alternative method', [
                'error' => $e->getMessage(),
                'filename' => $fileName,
                'target_directory' => $targetDirectory,
                'temp_path' => $file->getPathname(),
                'temp_exists' => file_exists($file->getPathname())
            ]);
            
            // Alternative method: try to copy the file content directly
            try {
                $tempPath = $file->getPathname();
                $targetPath = $targetDirectory . '/' . $fileName;
                
                if (file_exists($tempPath)) {
                    if (!copy($tempPath, $targetPath)) {
                        throw new \RuntimeException('Échec de la copie du fichier.');
                    }
                    $this->logger->info('File copied successfully using alternative method', [
                        'final_path' => $targetPath
                    ]);
                } else {
                    throw new \RuntimeException('Le fichier temporaire n\'existe plus.');
                }
            } catch (\Exception $copyException) {
                $this->logger->error('Alternative file copy also failed', [
                    'copy_error' => $copyException->getMessage(),
                    'original_error' => $e->getMessage()
                ]);
                throw new \RuntimeException('Erreur lors du téléchargement du fichier: ' . $e->getMessage());
            }
        }

        // Create MessageAttachment entity using stored file information
        $attachment = new MessageAttachment();
        $attachment->setMessage($message);
        $attachment->setFilename($fileName);
        $attachment->setOriginalFilename($clientOriginalName);
        $attachment->setFilePath($subDirectory . '/' . $fileName);
        $attachment->setMimeType($mimeType);
        $attachment->setFileSize($fileSize);
        $attachment->setUploadedAt($this->timeService->now());
        $attachment->setAttachmentType($this->determineAttachmentType($mimeType));

        return $attachment;
    }

    /**
     * Determines the attachment type based on MIME type
     */
    private function determineAttachmentType(string $mimeType): string
    {
        if (in_array($mimeType, self::IMAGE_MIME_TYPES)) {
            return 'image';
        }

        if ($mimeType === 'application/pdf') {
            return 'document';
        }

        if (str_starts_with($mimeType, 'application/') && 
            (str_contains($mimeType, 'word') || str_contains($mimeType, 'document'))) {
            return 'document';
        }

        if (str_starts_with($mimeType, 'text/')) {
            return 'document';
        }

        return 'other';
    }

    /**
     * Deletes a file from the filesystem
     */
    public function deleteFile(MessageAttachment $attachment): bool
    {
        $fullPath = $this->uploadDirectory . '/messages/' . $attachment->getFilePath();
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return true; // File doesn't exist, consider it deleted
    }

    /**
     * Gets the full path to an uploaded file
     */
    public function getFilePath(MessageAttachment $attachment): string
    {
        return $this->uploadDirectory . '/messages/' . $attachment->getFilePath();
    }

    /**
     * Checks if a file exists on the filesystem
     */
    public function fileExists(MessageAttachment $attachment): bool
    {
        return file_exists($this->getFilePath($attachment));
    }

    /**
     * Gets allowed file extensions for frontend validation
     */
    public function getAllowedExtensions(): array
    {
        return [
            'jpg', 'jpeg', 'png', 'gif', 'webp', // Images
            'pdf', // PDF
            'txt', // Text
            'doc', 'docx' // Word documents
        ];
    }

    /**
     * Gets max file size in bytes
     */
    public function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    /**
     * Gets max file size formatted for display
     */
    public function getMaxFileSizeFormatted(): string
    {
        return (self::MAX_FILE_SIZE / 1024 / 1024) . ' MB';
    }
}