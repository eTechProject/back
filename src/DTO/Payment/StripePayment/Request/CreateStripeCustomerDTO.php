<?php

namespace App\DTO\Payment\StripePayment\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateStripeCustomerDTO
{
    #[Assert\NotBlank(message: 'L\'email est requis')]
    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas valide')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $email;

    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $name = null;

    #[Assert\Regex(
        pattern: '/^\+?[1-9]\d{1,14}$/',
        message: 'Le numéro de téléphone n\'est pas valide'
    )]
    public ?string $phone = null;
}
