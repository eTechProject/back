<?php

namespace App\DTO\Agent\Request;

use Symfony\Component\Validator\Constraints as Assert;

class AgentProfileDTO
{
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $address;

    #[Assert\Url(message: 'L\'URL de la photo n\'est pas valide')]
    public ?string $profilePictureUrl;
}
