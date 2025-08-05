<?php

namespace App\DTO\Agent\Request;

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

    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $name;

    #[Assert\Regex(
        pattern: '/^\+?[0-9\s\-\(\)]{7,}$/',
        message: 'Le format du numéro de téléphone n\'est pas valide'
    )]
    public ?string $phone;

    #[Assert\Length(
        min: 8,
        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères'
    )]
    public ?string $password;
}
