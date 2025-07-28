<?php 
namespace App\DTO\Message;

class MessageDTO
{
    public function __construct(
        public string $encryptedId,
        public int $order_id,
        public int $sender_id,
        public int $receiver_id,
        public string $content,
        public string $sent_at,
    ) {}
}
