<?php

namespace App\DTO\Client\Internal;

use App\DTO\SecuredZone\Response\SecuredZoneDTO;
use DateTimeImmutable;

class ServiceOrderMapDTO
{
    public function __construct(
        public string $id,
        public ?string $description,
        public string $status,
        public DateTimeImmutable $createdAt,
        public ClientInfoDTO $client,
    ) {}
}
