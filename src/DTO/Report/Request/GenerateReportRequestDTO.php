<?php

declare(strict_types=1);

namespace App\DTO\Report\Request;

class GenerateReportRequestDTO
{
    public function __construct(
        public readonly string $type,
        public readonly string $format = 'json',
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly array $filters = []
    ) {
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate ? new \DateTime($this->startDate) : null;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate ? new \DateTime($this->endDate) : null;
    }

    public function isValidFormat(): bool
    {
        return in_array($this->format, ['json', 'pdf', 'excel', 'csv']);
    }

    public function isValidType(): bool
    {
        return in_array($this->type, ['users', 'orders', 'activities', 'agents']);
    }
}
