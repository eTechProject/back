<?php

declare(strict_types=1);

namespace App\DTO\Dashboard\Internal;

class DashboardStatDTO
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly int $value,
        public readonly float $variation,
        public readonly string $icon,
        public readonly string $color = 'primary'
    ) {
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'variation' => $this->variation,
            'icon' => $this->icon,
            'color' => $this->color,
            'trend' => $this->variation >= 0 ? 'up' : 'down'
        ];
    }

    public function isPositiveTrend(): bool
    {
        return $this->variation >= 0;
    }
}
