<?php

declare(strict_types=1);

namespace App\DTO\Report\Internal;

class ReportTypeDTO
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $description,
        public readonly array $availableFormats = ['json', 'pdf', 'excel', 'csv'],
        public readonly bool $enabled = true
    ) {
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'available_formats' => $this->availableFormats,
            'enabled' => $this->enabled
        ];
    }

    public function supportsFormat(string $format): bool
    {
        return in_array($format, $this->availableFormats);
    }
}
