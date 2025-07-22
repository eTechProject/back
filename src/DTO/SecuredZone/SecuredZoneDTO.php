<?php

namespace App\DTO\SecuredZone;

use DateTimeImmutable;

class SecuredZoneDTO
{
    public function __construct(
        public string $encryptedId,
        public string $name,
        public array $coordinates,
        public DateTimeImmutable $createdAt,
    ) {}
}
