<?php

namespace App\DTO\Dashboard\Response;

class DashboardStatsResponseDTO
{
    public array $stats;
    public \DateTimeInterface $startDate;
    public \DateTimeInterface $endDate;
    public ?string $period;

    public function __construct(array $stats, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?string $period = null)
    {
        $this->stats = $stats;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->period = $period;
    }

    public function toArray(): array
    {
        return [
            'stats' => array_map(fn($stat) => method_exists($stat, 'toArray') ? $stat->toArray() : $stat, $this->stats),
            'startDate' => $this->startDate->format('Y-m-d'),
            'endDate' => $this->endDate->format('Y-m-d'),
            'period' => $this->period,
        ];
    }
}
