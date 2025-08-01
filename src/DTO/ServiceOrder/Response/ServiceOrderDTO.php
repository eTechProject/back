<?php

namespace App\DTO\ServiceOrder\Response;

use App\DTO\SecuredZone\Response\SecuredZoneDTO;
use App\Enum\Status;
use DateTimeImmutable;

class ServiceOrderDTO
{
    public function __construct(
        public string $serviceOrderId,
        public ?string $description,
        public Status $status,
        public DateTimeImmutable $createdAt,
        public SecuredZoneDTO $securedZone,
        public string $clientId,
        public string $clientName,
    ) {}
}
