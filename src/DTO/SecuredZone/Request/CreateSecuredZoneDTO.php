<?php

namespace App\DTO\SecuredZone\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateSecuredZoneDTO
{
    #[Assert\NotBlank(message: 'Le nom de la zone sécurisée est requis')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $name;

    #[Assert\NotBlank(message: 'La géométrie de la zone est requise')]
    #[Assert\Type(
        type: 'array',
        message: 'La géométrie doit être un tableau de coordonnées'
    )]
    public array $coordinates;
}
