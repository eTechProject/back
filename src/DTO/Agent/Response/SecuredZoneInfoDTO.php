<?php

namespace App\DTO\Agent\Response;

class SecuredZoneInfoDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name
    ) {}
}
