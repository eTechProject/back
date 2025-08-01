<?php

namespace App\DTO\Client\Internal;

use DateTimeImmutable;

class AgentPositionDTO
{
    public function __construct(
        public float $longitude,
        public float $latitude,
        public DateTimeImmutable $recordedAt,
        public string $reason,
    ) {}
}
