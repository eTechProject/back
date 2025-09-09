<?php

declare(strict_types=1);

namespace App\DTO\Dashboard\Response;

use App\DTO\Dashboard\Internal\ActivityDTO;

class RecentActivitiesResponseDTO
{
    /**
     * @param ActivityDTO[] $activities
     */
    public function __construct(
        public readonly array $activities,
        public readonly int $limit,
        public readonly \DateTime $generatedAt = new \DateTime()
    ) {
    }

    public function toArray(): array
    {
        return [
            'activities' => array_map(fn(ActivityDTO $activity) => $activity->toArray(), $this->activities),
            'meta' => [
                'total' => count($this->activities),
                'limit' => $this->limit,
                'generated_at' => $this->generatedAt->format('Y-m-d H:i:s')
            ]
        ];
    }
}
