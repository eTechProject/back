<?php

namespace App\DTO\Agent\Request;

use Symfony\Component\Validator\Constraints as Assert;
use App\Enum\Genre;

class UpdateAgentDTO
{
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $address = null;

    #[Assert\Url(message: 'L\'URL de la photo n\'est pas valide')]
    public ?string $profilePictureUrl = null;

    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $name = null;

    #[Assert\Regex(
        pattern: '/^\+?[0-9\s\-\(\)]{7,}$/',
        message: 'Le format du numéro de téléphone n\'est pas valide'
    )]
    public ?string $phone = null;

    #[Assert\Choice(
        choices: ['M', 'F'],
        message: 'Le sexe doit être M ou F'
    )]
    public ?string $sexe = null;
}
