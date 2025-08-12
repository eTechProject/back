<?php

namespace App\DTO\Pack\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreatePackDTO
{
    #[Assert\NotNull(message: 'Le nombre d\'agents est requis')]
    #[Assert\PositiveOrZero(message: 'Le nombre d\'agents doit être positif ou nul')]
    public int $nbAgents;

    #[Assert\NotNull(message: 'Le prix est requis')]
    #[Assert\PositiveOrZero(message: 'Le prix doit être positif ou nul')]
    public float $prix;

    #[Assert\NotBlank(message: 'La description est requise')]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $description;
}
