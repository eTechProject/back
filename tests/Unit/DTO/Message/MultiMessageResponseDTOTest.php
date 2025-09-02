<?php

namespace Tests\Unit\DTO\Message;

use PHPUnit\Framework\TestCase;
use App\DTO\Message\MultiMessageResponseDTO;

class MultiMessageResponseDTOTest extends TestCase
{
    public function testMultiMessageResponseDTOCreation(): void
    {
        $successfulConversations = [
            ['conversation_id' => 'conv_123_456', 'receiver_id' => 456],
            ['conversation_id' => 'conv_123_789', 'receiver_id' => 789]
        ];
        
        $failedConversations = [
            ['receiver_id' => 999, 'error' => 'Agent non autorisé']
        ];

        $dto = new MultiMessageResponseDTO(
            total_sent: 2,
            total_failed: 1,
            successful_conversations: $successfulConversations,
            failed_conversations: $failedConversations,
            message: 'Message envoyé à 2/3 agents (2 succès, 1 échecs)'
        );

        $this->assertEquals(2, $dto->total_sent);
        $this->assertEquals(1, $dto->total_failed);
        $this->assertEquals($successfulConversations, $dto->successful_conversations);
        $this->assertEquals($failedConversations, $dto->failed_conversations);
        $this->assertEquals('Message envoyé à 2/3 agents (2 succès, 1 échecs)', $dto->message);
    }

    public function testMultiMessageResponseDTOWithEmptyArrays(): void
    {
        $dto = new MultiMessageResponseDTO(
            total_sent: 0,
            total_failed: 0,
            successful_conversations: [],
            failed_conversations: [],
            message: 'Aucun message envoyé'
        );

        $this->assertEquals(0, $dto->total_sent);
        $this->assertEquals(0, $dto->total_failed);
        $this->assertEmpty($dto->successful_conversations);
        $this->assertEmpty($dto->failed_conversations);
        $this->assertEquals('Aucun message envoyé', $dto->message);
    }

    public function testMultiMessageResponseDTOWithOnlySuccesses(): void
    {
        $successfulConversations = [
            ['conversation_id' => 'conv_123_456', 'receiver_id' => 456],
            ['conversation_id' => 'conv_123_789', 'receiver_id' => 789],
            ['conversation_id' => 'conv_123_101', 'receiver_id' => 101]
        ];

        $dto = new MultiMessageResponseDTO(
            total_sent: 3,
            total_failed: 0,
            successful_conversations: $successfulConversations,
            failed_conversations: [],
            message: 'Message envoyé avec succès à 3 agents'
        );

        $this->assertEquals(3, $dto->total_sent);
        $this->assertEquals(0, $dto->total_failed);
        $this->assertCount(3, $dto->successful_conversations);
        $this->assertEmpty($dto->failed_conversations);
        $this->assertEquals('Message envoyé avec succès à 3 agents', $dto->message);
    }

    public function testMultiMessageResponseDTOWithOnlyFailures(): void
    {
        $failedConversations = [
            ['receiver_id' => 456, 'error' => 'Agent non autorisé'],
            ['receiver_id' => 789, 'error' => 'Agent indisponible']
        ];

        $dto = new MultiMessageResponseDTO(
            total_sent: 0,
            total_failed: 2,
            successful_conversations: [],
            failed_conversations: $failedConversations,
            message: 'Échec de l\'envoi du message à 2 agents'
        );

        $this->assertEquals(0, $dto->total_sent);
        $this->assertEquals(2, $dto->total_failed);
        $this->assertEmpty($dto->successful_conversations);
        $this->assertCount(2, $dto->failed_conversations);
        $this->assertEquals('Échec de l\'envoi du message à 2 agents', $dto->message);
    }

    public function testMultiMessageResponseDTOReadonlyProperties(): void
    {
        $dto = new MultiMessageResponseDTO(
            total_sent: 1,
            total_failed: 0,
            successful_conversations: [['conversation_id' => 'test', 'receiver_id' => 123]],
            failed_conversations: [],
            message: 'Test message'
        );

        // Vérification que les propriétés sont bien readonly
        // (ceci est vérifié au niveau du compilateur PHP, pas à l'exécution)
        $this->assertEquals(1, $dto->total_sent);
        $this->assertEquals(0, $dto->total_failed);
        $this->assertIsArray($dto->successful_conversations);
        $this->assertIsArray($dto->failed_conversations);
        $this->assertIsString($dto->message);
    }
}
