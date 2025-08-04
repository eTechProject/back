<?php

namespace App\DTO\Agent\Response;

use DateTimeImmutable;

class AgentPositionHistoryDTO
{
    public function __construct(
        public float $longitude,
        public float $latitude,
        public DateTimeImmutable $recordedAt,
        public string $reason,
    ) {}
}
