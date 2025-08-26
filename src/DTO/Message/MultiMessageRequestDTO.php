<?php

namespace App\DTO\Message;

use Symfony\Component\Validator\Constraints as Assert;

class MultiMessageRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "L'ID de l'expéditeur est requis")]
        public readonly string $sender_id,

        #[Assert\NotBlank(message: "Au moins un destinataire est requis")]
        #[Assert\Type(type: "array", message: "receiver_ids doit être un tableau")]
        #[Assert\Count(min: 1, minMessage: "Au moins un destinataire est requis")]
        #[Assert\All([
            new Assert\NotBlank(message: "L'ID du destinataire ne peut pas être vide")
        ])]
        public readonly array $receiver_ids,

        #[Assert\NotBlank(message: "L'ID de la commande est requis")]
        public readonly string $order_id,

        #[Assert\NotBlank(message: "Le contenu du message est requis")]
        #[Assert\Length(
            min: 1,
            max: 5000,
            minMessage: "Le message ne peut pas être vide",
            maxMessage: "Le message ne peut pas dépasser 5000 caractères"
        )]
        public readonly string $content
    ) {}
}
