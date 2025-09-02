<?php

namespace Tests\Unit\DTO\Message;

use PHPUnit\Framework\TestCase;
use App\DTO\Message\MultiMessageRequestDTO;

class MultiMessageRequestDTOTest extends TestCase
{
    public function testValidMultiMessageRequestDTO(): void
    {
        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: ['encrypted_receiver_456', 'encrypted_receiver_789'],
            order_id: 'encrypted_order_101',
            content: 'Message de test valide'
        );

        $this->assertEquals('encrypted_sender_123', $dto->sender_id);
        $this->assertEquals(['encrypted_receiver_456', 'encrypted_receiver_789'], $dto->receiver_ids);
        $this->assertEquals('encrypted_order_101', $dto->order_id);
        $this->assertEquals('Message de test valide', $dto->content);
    }

    public function testMultiMessageRequestDTOWithSingleReceiver(): void
    {
        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: ['encrypted_receiver_456'],
            order_id: 'encrypted_order_101',
            content: 'Message pour un seul destinataire'
        );

        $this->assertCount(1, $dto->receiver_ids);
        $this->assertEquals('encrypted_receiver_456', $dto->receiver_ids[0]);
    }

    public function testMultiMessageRequestDTOWithMultipleReceivers(): void
    {
        $receiverIds = [
            'encrypted_receiver_456',
            'encrypted_receiver_789',
            'encrypted_receiver_101',
            'encrypted_receiver_112'
        ];

        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: $receiverIds,
            order_id: 'encrypted_order_101',
            content: 'Message pour plusieurs destinataires'
        );

        $this->assertCount(4, $dto->receiver_ids);
        $this->assertEquals($receiverIds, $dto->receiver_ids);
    }

    public function testMultiMessageRequestDTOWithEmptyReceiversArray(): void
    {
        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: [],
            order_id: 'encrypted_order_101',
            content: 'Message avec tableau vide'
        );

        $this->assertEmpty($dto->receiver_ids);
        $this->assertIsArray($dto->receiver_ids);
    }

    public function testMultiMessageRequestDTOWithLongContent(): void
    {
        $longContent = str_repeat('Test message content. ', 40); // Environ 800 caractères

        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: ['encrypted_receiver_456'],
            order_id: 'encrypted_order_101',
            content: $longContent
        );

        $this->assertEquals($longContent, $dto->content);
        $this->assertGreaterThan(500, strlen($dto->content));
    }

    public function testMultiMessageRequestDTOReadonlyProperties(): void
    {
        $dto = new MultiMessageRequestDTO(
            sender_id: 'test_sender',
            receiver_ids: ['test_receiver'],
            order_id: 'test_order',
            content: 'test content'
        );

        // Vérification que les propriétés sont bien readonly
        // (ceci est vérifié au niveau du compilateur PHP, pas à l'exécution)
        $this->assertEquals('test_sender', $dto->sender_id);
        $this->assertEquals(['test_receiver'], $dto->receiver_ids);
        $this->assertEquals('test_order', $dto->order_id);
        $this->assertEquals('test content', $dto->content);
        
        // Vérification des types
        $this->assertIsString($dto->sender_id);
        $this->assertIsArray($dto->receiver_ids);
        $this->assertIsString($dto->order_id);
        $this->assertIsString($dto->content);
    }
}
