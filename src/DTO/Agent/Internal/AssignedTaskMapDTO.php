<?php

namespace App\DTO\Agent\Internal;

use App\DTO\Client\Internal\ClientInfoDTO;
use DateTimeImmutable;

class AssignedTaskMapDTO
{
    public function __construct(
        public string $id,
        public ?string $description,
        public string $status,
        public DateTimeImmutable $createdAt,
        public ClientInfoDTO $client,
    ) {}
}
