<?php

declare(strict_types=1);

namespace App\DTO\Report\Response;

use App\DTO\Report\Internal\ReportDTO;

class GenerateReportResponseDTO
{
    public function __construct(
        public readonly ReportDTO $report,
        public readonly string $downloadUrl,
        public readonly \DateTime $generatedAt = new \DateTime()
    ) {
    }

    public function toArray(): array
    {
        return [
            'report' => $this->report->toArray(),
            'download_url' => $this->downloadUrl,
            'generated_at' => $this->generatedAt->format('Y-m-d H:i:s'),
            'status' => 'success'
        ];
    }
}
