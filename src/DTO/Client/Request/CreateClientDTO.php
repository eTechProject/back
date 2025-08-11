<?php

namespace App\DTO\Client\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateClientDTO
{
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $name;

    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $email;

    #[Assert\Regex(
        pattern: '/^\+?[0-9\s\-\(\)]{7,}$/',
        message: 'Le format du numéro de téléphone n\'est pas valide'
    )]
    public ?string $phone = null;

    #[Assert\Length(
        min: 8,
        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères'
    )]
    public ?string $password = null;
}
