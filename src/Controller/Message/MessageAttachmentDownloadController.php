<?php

namespace App\Controller\Message;

use App\Entity\MessageAttachment;
use App\Entity\User;
use App\Service\CryptService;
use App\Service\FileUploadService;
use App\Service\MessageHandlerService;
use App\Enum\EntityType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/messages/attachments/{encryptedId}', name: 'api_messages_attachment_download', methods: ['GET'])]
class MessageAttachmentDownloadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CryptService $cryptService,
        private readonly FileUploadService $fileUploadService,
        private readonly MessageHandlerService $messageHandler,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(string $encryptedId): Response
    {
        try {
            // Decrypt the attachment ID
            $attachmentId = $this->cryptService->decryptId($encryptedId, EntityType::MESSAGE->value);
        } catch (\Exception) {
            return $this->json([
                'status' => 'error',
                'message' => 'Identifiant de pièce jointe invalide'
            ], 400);
        }

        // Find the attachment
        $attachment = $this->em->getRepository(MessageAttachment::class)->find($attachmentId);
        if (!$attachment) {
            return $this->json([
                'status' => 'error',
                'message' => 'Pièce jointe non trouvée'
            ], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'status' => 'error',
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        // Check if user has access to this message/order
        $message = $attachment->getMessage();
        if (!$message) {
            return $this->json([
                'status' => 'error',
                'message' => 'Message associé non trouvé'
            ], 404);
        }

        $order = $message->getOrder();
        if (!$order) {
            return $this->json([
                'status' => 'error',
                'message' => 'Commande associée non trouvée'
            ], 404);
        }

        // Validate user access to the order
        $validationResponse = $this->messageHandler->validateAccess($user, $order->getId());
        if ($validationResponse) {
            return $validationResponse;
        }

        // Check if file exists on filesystem
        if (!$this->fileUploadService->fileExists($attachment)) {
            $this->logger->error('Fichier de pièce jointe non trouvé sur le système de fichiers', [
                'attachment_id' => $attachment->getId(),
                'file_path' => $attachment->getFilePath(),
                'user_id' => $user->getId()
            ]);
            
            return $this->json([
                'status' => 'error',
                'message' => 'Fichier non accessible'
            ], 404);
        }

        try {
            $filePath = $this->fileUploadService->getFilePath($attachment);
            
            // Create binary file response
            $response = new BinaryFileResponse($filePath);
            
            // Set appropriate headers
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $attachment->getOriginalFilename()
            );
            
            // Set content type
            $response->headers->set('Content-Type', $attachment->getMimeType());
            
            // Set cache headers
            $response->setMaxAge(3600); // Cache for 1 hour
            $response->setSharedMaxAge(3600);
            
            // Log successful download
            $this->logger->info('Pièce jointe téléchargée avec succès', [
                'attachment_id' => $attachment->getId(),
                'filename' => $attachment->getOriginalFilename(),
                'user_id' => $user->getId(),
                'message_id' => $message->getId(),
                'order_id' => $order->getId()
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du téléchargement de la pièce jointe', [
                'attachment_id' => $attachment->getId(),
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors du téléchargement du fichier'
            ], 500);
        }
    }
}