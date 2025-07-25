<?php

namespace App\DTO\ServiceOrder;

use App\DTO\SecuredZone\SecuredZoneDTO;
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
