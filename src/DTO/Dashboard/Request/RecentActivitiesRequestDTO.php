<?php

declare(strict_types=1);

namespace App\DTO\Dashboard\Request;

class RecentActivitiesRequestDTO
{
    public function __construct(
        public readonly int $limit = 10,
        public readonly ?string $type = null,
        public readonly ?int $userId = null,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null
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
}
