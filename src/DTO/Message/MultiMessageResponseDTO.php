<?php

namespace App\DTO\Message;

class MultiMessageResponseDTO
{
    public function __construct(
        public readonly int $total_sent,
        public readonly int $total_failed,
        public readonly array $successful_conversations,
        public readonly array $failed_conversations,
        public readonly string $message
    ) {}
}
