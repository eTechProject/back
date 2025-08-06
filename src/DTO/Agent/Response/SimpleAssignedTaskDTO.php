<?php

namespace App\DTO\Agent\Response;

class SimpleAssignedTaskDTO
{
    public function __construct(
        public readonly string $orderId, // This is the service order ID
        public readonly string $status,
        public readonly SimpleClientDTO $client
    ) {}
}
