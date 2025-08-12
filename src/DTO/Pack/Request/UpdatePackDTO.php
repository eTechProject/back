<?php

namespace App\DTO\Pack\Request;

use Symfony\Component\Validator\Constraints as Assert;

class UpdatePackDTO
{
    public function __construct(
        #[Assert\Type('integer')]
        #[Assert\GreaterThan(0, message: 'Le nombre d\'agents doit être supérieur à 0')]
        public readonly ?int $nb_agents = null,

        #[Assert\Type('float')]
        #[Assert\GreaterThan(0, message: 'Le prix doit être supérieur à 0')]
        public readonly ?float $prix = null,

        #[Assert\Type('string')]
        #[Assert\Length(max: 1000, maxMessage: 'La description ne peut pas dépasser 1000 caractères')]
        public readonly ?string $description = null
    ) {}
}
