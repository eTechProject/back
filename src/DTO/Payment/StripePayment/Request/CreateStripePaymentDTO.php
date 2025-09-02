<?php

namespace App\DTO\Payment\StripePayment\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateStripePaymentDTO
{
    #[Assert\NotBlank(message: 'Le montant est requis')]
    #[Assert\PositiveOrZero(message: 'Le montant doit être positif')]
    #[Assert\Type(type: 'float', message: 'Le montant doit être un nombre')]
    public float $amount;

    #[Assert\NotBlank(message: 'La devise est requise')]
    #[Assert\Length(
        max: 3,
        exactMessage: 'La devise doit faire exactement {{ limit }} caractères'
    )]
    #[Assert\Choice(
        choices: ['EUR', 'USD', 'GBP', 'CAD'],
        message: 'La devise doit être EUR, USD, GBP ou CAD'
    )]
    public string $currency = 'EUR';

    #[Assert\NotBlank(message: 'Le numéro de carte est requis')]
    #[Assert\Length(
        min: 13,
        max: 19,
        minMessage: 'Le numéro de carte doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le numéro de carte ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Assert\Regex(
        pattern: '/^\d+$/',
        message: 'Le numéro de carte ne doit contenir que des chiffres'
    )]
    public string $cardNumber;

    #[Assert\NotBlank(message: 'L\'ID du pack est requis')]
    public string $packId;

    #[Assert\NotBlank(message: 'Le mois d\'expiration est requis')]
    #[Assert\Range(
        min: 1,
        max: 12,
        notInRangeMessage: 'Le mois d\'expiration doit être entre {{ min }} et {{ max }}'
    )]
    #[Assert\Type(type: 'integer', message: 'Le mois d\'expiration doit être un entier')]
    public int $expiryMonth;

    #[Assert\NotBlank(message: 'L\'année d\'expiration est requise')]
    #[Assert\Range(
        min: 2024,
        max: 2050,
        notInRangeMessage: 'L\'année d\'expiration doit être entre {{ min }} et {{ max }}'
    )]
    #[Assert\Type(type: 'integer', message: 'L\'année d\'expiration doit être un entier')]
    public int $expiryYear;

    #[Assert\NotBlank(message: 'Le CVV est requis')]
    #[Assert\Length(
        min: 3,
        max: 4,
        minMessage: 'Le CVV doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le CVV ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Assert\Regex(
        pattern: '/^\d+$/',
        message: 'Le CVV ne doit contenir que des chiffres'
    )]
    public string $cvv;

    #[Assert\Email(message: 'L\'email doit être valide')]
    public ?string $customerEmail = null;

    #[Assert\Length(
        max: 500,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $description = null;
}
