<?php

namespace App\DTO\Notification\Response;

use App\Enum\NotificationType;
use App\Enum\NotificationTarget;

class NotificationResponseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $titre,
        public readonly string $message,
        public readonly NotificationType $type,
        public readonly NotificationTarget $cible,
        public readonly bool $isRead,
        public readonly \DateTimeInterface $createdAt,
        public readonly ?string $userId = null
    ) {}
}
