<?php

declare(strict_types=1);

namespace App\DTO\Dashboard\Internal;

class ActivityDTO
{
    public function __construct(
        public readonly string $type,
        public readonly string $description,
        public readonly \DateTimeImmutable $createdAt,
        public readonly string $userIdentifier,
        public readonly array $metadata = [],
        public readonly string $severity = 'info'
    ) {
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'description' => $this->description,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'user' => $this->userIdentifier,
            'metadata' => $this->metadata,
            'severity' => $this->severity,
            'time_ago' => $this->getTimeAgo()
        ];
    }

    private function getTimeAgo(): string
    {
        $now = new \DateTime();
        $diff = $now->diff($this->createdAt);

        if ($diff->days > 0) {
            return $diff->days . ' jour(s)';
        } elseif ($diff->h > 0) {
            return $diff->h . ' heure(s)';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute(s)';
        } else {
            return 'À l\'instant';
        }
    }
}
