<?php

namespace App\DTO\Client\Internal;

class ClientInfoDTO
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
