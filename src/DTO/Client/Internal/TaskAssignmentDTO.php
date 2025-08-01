<?php

namespace App\DTO\Client\Internal;

use DateTimeImmutable;

class TaskAssignmentDTO
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $description,
        public DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
        public array $assignPosition,
    ) {}
}
