<?php

declare(strict_types=1);

namespace App\DTO\Dashboard\Response;

use App\DTO\Dashboard\Internal\DashboardStatDTO;
use App\DTO\Dashboard\Internal\ActivityDTO;

class DashboardOverviewResponseDTO
{
    /**
     * @param DashboardStatDTO[] $stats
     * @param ActivityDTO[] $recentActivities
     */
    public function __construct(
        public readonly array $stats,
        public readonly array $recentActivities,
        public readonly \DateTime $lastUpdated
    ) {
    }

    public function toArray(): array
    {
        return [
            'stats' => array_map(fn(DashboardStatDTO $stat) => $stat->toArray(), $this->stats),
            'recent_activities' => array_map(fn(ActivityDTO $activity) => $activity->toArray(), $this->recentActivities),
            'meta' => [
                'last_updated' => $this->lastUpdated->format('Y-m-d H:i:s'),
                'stats_count' => count($this->stats),
                'activities_count' => count($this->recentActivities)
            ]
        ];
    }
}
