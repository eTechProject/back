<?php 

namespace App\DTO\Message;

class MessageAttachmentDTO
{
    public function __construct(
        public string $id,
        public string $filename,
        public string $originalFilename,
        public string $mimeType,
        public string $attachmentType,
        public int $fileSize,
        public string $formattedFileSize,
        public string $uploadedAt,
        public string $downloadUrl,
    ) {}
}