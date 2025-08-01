<?php

namespace App\DTO\SecuredZone\Response;

use DateTimeImmutable;

class SecuredZoneDTO
{
    public function __construct(
        public string $securedZoneId,
        public string $name,
        public array $coordinates,
        public DateTimeImmutable $createdAt,
    ) {}
}
