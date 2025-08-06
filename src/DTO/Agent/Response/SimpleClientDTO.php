<?php

namespace App\DTO\Agent\Response;

class SimpleClientDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email
    ) {}
}
