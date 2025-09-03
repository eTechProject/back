<?php

declare(strict_types=1);

namespace App\DTO\Report\Response;

use App\DTO\Report\Internal\ReportTypeDTO;

class ReportTypesResponseDTO
{
    /**
     * @param ReportTypeDTO[] $types
     */
    public function __construct(
        public readonly array $types
    ) {
    }

    public function toArray(): array
    {
        return [
            'report_types' => array_map(fn(ReportTypeDTO $type) => $type->toArray(), $this->types),
            'total_types' => count($this->types),
            'available_formats' => ['json', 'pdf', 'excel', 'csv']
        ];
    }
}
