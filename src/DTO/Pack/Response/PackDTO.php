<?php

namespace App\DTO\Pack\Response;

use App\Enum\EntityType;

class PackDTO
{
    public function __construct(
        public string $id,
        public int $nbAgents,
        public string $prix,
        public \DateTimeInterface $dateCreation,
        public string $description
    ) {}
}
