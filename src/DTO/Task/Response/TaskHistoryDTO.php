<?php

namespace App\DTO\Task\Response;

class TaskHistoryDTO
{
    public function __construct(
        public string $taskId,
        public string $description,
        public string $status,
        public string $type,
        public string $startDate,
        public ?string $endDate,
        public string $orderId,
        public string $orderDescription
    ) {}
}
