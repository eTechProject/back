<?php

namespace App\DTO\Agent\Response;

use App\DTO\Client\Internal\ClientInfoDTO;

class AssignedTaskDTO
{
    public function __construct(
        public readonly string $taskId,
        public readonly string $orderId,
        public readonly string $status,
        public readonly ?string $description,
        public readonly string $startDate,
        public readonly ?string $endDate,
        public readonly array $assignPosition, // [longitude, latitude]
        public readonly ClientInfoDTO $client,
        public readonly TaskOrderDTO $order
    ) {}
}
