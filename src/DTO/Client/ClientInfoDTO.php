<?php

namespace App\DTO\Client;

class ClientInfoDTO
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
