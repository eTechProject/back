<?php

declare(strict_types=1);

namespace App\DTO\Dashboard\Request;

class DashboardStatsRequestDTO
{
    public function __construct(
        public readonly string $period = 'month',
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly array $filters = []
    ) {
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate ? new \DateTime($this->startDate) : null;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate ? new \DateTime($this->endDate) : null;
    }

    public function getPeriodInDays(): int
    {
        return match ($this->period) {
            'day' => 1,
            'week' => 7,
            'month' => 30,
            'quarter' => 90,
            'year' => 365,
            default => 30
        };
    }
}
