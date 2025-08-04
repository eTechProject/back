<?php

namespace App\DTO\Agent\Response;

class TaskOrderDTO
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $description,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly SecuredZoneInfoDTO $securedZone
    ) {}
}
