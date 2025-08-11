<?php

namespace App\DTO\Notification\Request;

use App\Enum\NotificationType;
use App\Enum\NotificationTarget;
use Symfony\Component\Validator\Constraints as Assert;

class CreateNotificationDTO
{
    #[Assert\NotBlank(message: 'Le titre est requis')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractère',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $titre;

    #[Assert\NotBlank(message: 'Le message est requis')]
    #[Assert\Length(
        min: 1,
        max: 1000,
        minMessage: 'Le message doit contenir au moins {{ limit }} caractère',
        maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $message;

    #[Assert\NotNull(message: 'Le type est requis')]
    public NotificationType $type = NotificationType::INFO;

    #[Assert\NotNull(message: 'La cible est requise')]
    public NotificationTarget $cible = NotificationTarget::USER;

    public ?string $userId = null;
}
