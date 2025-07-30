<?php

namespace App\DTO\Client;

use DateTimeImmutable;

class TaskAssignmentDTO
{
    public function __construct(
        public int $id,
        public string $status,
        public ?string $description,
        public DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
        public string $assignPosition,
    ) {}
}
