<?php

namespace App\DTO\User\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserProfileDTO
{
    #[Assert\NotBlank(message: 'Le nom est requis')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'L\'email est requis')]
    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas valide')]
    public ?string $email = null;

    #[Assert\Regex(
        pattern: '/^\+?[0-9\s\-\(\)]{7,}$/',
        message: 'Le numéro de téléphone n\'est pas valide'
    )]
    public ?string $phone = null;
}
