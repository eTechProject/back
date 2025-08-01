<?php

namespace App\DTO\Agent;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateAgentDTO
{
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $address;

    #[Assert\Url(message: 'L\'URL de la photo n\'est pas valide')]
    public ?string $profilePictureUrl;
}
