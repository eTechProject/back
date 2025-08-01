<?php 
namespace App\DTO\Message;

class MessageDTO
{
    public function __construct(
        public string $encryptedId,
        public string $order_id,
        public string $sender_id,
        public string $receiver_id,
        public string $content,
        public string $sent_at,
    ) {}
}
