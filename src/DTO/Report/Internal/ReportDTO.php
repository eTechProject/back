<?php

declare(strict_types=1);

namespace App\DTO\Report\Internal;

class ReportDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $format,
        public readonly array $data,
        public readonly \DateTime $generatedAt,
        public readonly int $totalItems,
        public readonly array $metadata = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'format' => $this->format,
            'data' => $this->data,
            'generated_at' => $this->generatedAt->format('Y-m-d H:i:s'),
            'total_items' => $this->totalItems,
            'metadata' => $this->metadata,
            'size' => $this->getDataSize()
        ];
    }

    public function getDataSize(): string
    {
        $bytes = strlen(json_encode($this->data));
        
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }
}
