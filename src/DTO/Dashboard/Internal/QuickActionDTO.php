<?php

declare(strict_types=1);

namespace App\DTO\Dashboard\Internal;

class QuickActionDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $url,
        public readonly string $method,
        public readonly string $icon,
        public readonly bool $enabled = true,
        public readonly array $permissions = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'url' => $this->url,
            'method' => $this->method,
            'icon' => $this->icon,
            'enabled' => $this->enabled,
            'permissions' => $this->permissions
        ];
    }

    public function isAccessible(array $userPermissions): bool
    {
        if (empty($this->permissions)) {
            return true;
        }

        return !empty(array_intersect($this->permissions, $userPermissions));
    }
}
