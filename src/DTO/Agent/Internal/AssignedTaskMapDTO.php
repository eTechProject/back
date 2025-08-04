<?php

namespace App\DTO\Agent\Internal;

use App\DTO\Agent\Response\SimpleClientDTO;
use DateTimeImmutable;

class AssignedTaskMapDTO
{
    public function __construct(
        public string $id,
        public string $orderId,
        public ?string $description,
        public string $status,
        public DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
        public array $assignPosition,
        public SimpleClientDTO $client,
    ) {}
}
