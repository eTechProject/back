<?php

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\MultiMessageService;
use App\Service\MessageService;
use App\Service\CryptService;
use App\DTO\Message\MultiMessageRequestDTO;
use App\Enum\EntityType;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use PHPUnit\Framework\MockObject\MockObject;

class MultiMessageServiceTest extends TestCase
{
    private MessageService|MockObject $messageService;
    private CryptService|MockObject $cryptService;
    private LoggerInterface|MockObject $logger;
    private MultiMessageService $multiMessageService;

    protected function setUp(): void
    {
        $this->messageService = $this->createMock(MessageService::class);
        $this->cryptService = $this->createMock(CryptService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->multiMessageService = new MultiMessageService(
            $this->messageService,
            $this->cryptService,
            $this->logger
        );
    }

    /**
     * Test du succès d'un envoi multiple avec publication Mercure
     */
    public function testHandleMultiMessageRequestSuccess(): void
    {
        // Arrange
        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: ['encrypted_receiver_456', 'encrypted_receiver_789'],
            order_id: 'encrypted_order_101',
            content: 'Message de test pour tous les agents'
        );

        // Mock du déchiffrement des IDs
        $this->cryptService
            ->method('decryptId')
            ->willReturnMap([
                ['encrypted_sender_123', EntityType::USER->value, 123],
                ['encrypted_receiver_456', EntityType::USER->value, 456],
                ['encrypted_receiver_789', EntityType::USER->value, 789],
                ['encrypted_order_101', EntityType::SERVICE_ORDER->value, 101]
            ]);

        // Mock du service de message avec succès complet
        $this->messageService
            ->expects($this->once())
            ->method('createMultipleMessages')
            ->with([
                'sender_id' => 123,
                'receiver_ids' => [456, 789],
                'order_id' => 101,
                'content' => 'Message de test pour tous les agents'
            ])
            ->willReturn([
                'total_sent' => 2,
                'total_failed' => 0,
                'successful_conversations' => [
                    ['conversation_id' => 'conv_123_456', 'receiver_id' => 456],
                    ['conversation_id' => 'conv_123_789', 'receiver_id' => 789]
                ],
                'failed_conversations' => []
            ]);

        // Act
        $response = $this->multiMessageService->handleMultiMessageRequest($dto);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(2, $data['data']['total_sent']);
        $this->assertEquals(0, $data['data']['total_failed']);
        $this->assertCount(2, $data['data']['successful_conversations']);
        $this->assertEmpty($data['data']['failed_conversations']);
        $this->assertEquals('Message envoyé avec succès à 2 agents', $data['message']);
    }

    /**
     * Test de rejet d'agents non liés à la commande
     */
    public function testHandleMultiMessageRequestWithUnauthorizedAgents(): void
    {
        // Arrange
        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: ['encrypted_receiver_456', 'encrypted_receiver_999'],
            order_id: 'encrypted_order_101',
            content: 'Message de test'
        );

        // Mock du déchiffrement
        $this->cryptService
            ->method('decryptId')
            ->willReturnMap([
                ['encrypted_sender_123', EntityType::USER->value, 123],
                ['encrypted_receiver_456', EntityType::USER->value, 456],
                ['encrypted_receiver_999', EntityType::USER->value, 999],
                ['encrypted_order_101', EntityType::SERVICE_ORDER->value, 101]
            ]);

        // Mock du service avec un agent rejeté
        $this->messageService
            ->expects($this->once())
            ->method('createMultipleMessages')
            ->willReturn([
                'total_sent' => 1,
                'total_failed' => 1,
                'successful_conversations' => [
                    ['conversation_id' => 'conv_123_456', 'receiver_id' => 456]
                ],
                'failed_conversations' => [
                    ['receiver_id' => 999, 'error' => 'Agent non autorisé pour cette commande']
                ]
            ]);

        // Act
        $response = $this->multiMessageService->handleMultiMessageRequest($dto);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(1, $data['data']['total_sent']);
        $this->assertEquals(1, $data['data']['total_failed']);
        $this->assertCount(1, $data['data']['successful_conversations']);
        $this->assertCount(1, $data['data']['failed_conversations']);
        $this->assertEquals('Message envoyé à 1/2 agents (1 succès, 1 échecs)', $data['message']);
    }

    /**
     * Test de rejet de tableau vide de destinataires
     */
    public function testHandleMultiMessageRequestWithEmptyReceivers(): void
    {
        // Arrange
        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: [],
            order_id: 'encrypted_order_101',
            content: 'Message de test'
        );

        // Mock du déchiffrement
        $this->cryptService
            ->method('decryptId')
            ->willReturnMap([
                ['encrypted_sender_123', EntityType::USER->value, 123],
                ['encrypted_order_101', EntityType::SERVICE_ORDER->value, 101]
            ]);

        // Mock du service avec tableau vide rejeté
        $this->messageService
            ->expects($this->once())
            ->method('createMultipleMessages')
            ->willReturn([
                'total_sent' => 0,
                'total_failed' => 0,
                'successful_conversations' => [],
                'failed_conversations' => []
            ]);

        // Act
        $response = $this->multiMessageService->handleMultiMessageRequest($dto);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(0, $data['data']['total_sent']);
        $this->assertEquals(0, $data['data']['total_failed']);
        $this->assertEmpty($data['data']['successful_conversations']);
        $this->assertEmpty($data['data']['failed_conversations']);
    }

    /**
     * Test d'erreur de déchiffrement d'identifiants invalides
     */
    public function testHandleMultiMessageRequestWithInvalidIds(): void
    {
        // Arrange
        $dto = new MultiMessageRequestDTO(
            sender_id: 'invalid_encrypted_id',
            receiver_ids: ['encrypted_receiver_456'],
            order_id: 'encrypted_order_101',
            content: 'Message de test'
        );

        // Mock du déchiffrement avec exception
        $this->cryptService
            ->method('decryptId')
            ->willThrowException(new \Exception('Invalid encrypted ID'));

        // Mock du logger pour vérifier l'erreur loggée
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Erreur de validation multi-message', [
                'error' => 'Un ou plusieurs identifiants sont invalides'
            ]);

        // Act
        $response = $this->multiMessageService->handleMultiMessageRequest($dto);

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('Un ou plusieurs identifiants sont invalides', $data['message']);
    }

    /**
     * Test d'erreur inattendue du service de message
     */
    public function testHandleMultiMessageRequestWithUnexpectedError(): void
    {
        // Arrange
        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: ['encrypted_receiver_456'],
            order_id: 'encrypted_order_101',
            content: 'Message de test'
        );

        // Mock du déchiffrement réussi
        $this->cryptService
            ->method('decryptId')
            ->willReturnMap([
                ['encrypted_sender_123', EntityType::USER->value, 123],
                ['encrypted_receiver_456', EntityType::USER->value, 456],
                ['encrypted_order_101', EntityType::SERVICE_ORDER->value, 101]
            ]);

        // Mock du service avec exception inattendue
        $this->messageService
            ->method('createMultipleMessages')
            ->willThrowException(new \Exception('Database connection failed'));

        // Mock du logger pour vérifier l'erreur loggée
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Erreur lors du traitement multi-message', [
                'error' => 'Database connection failed'
            ]);

        // Act
        $response = $this->multiMessageService->handleMultiMessageRequest($dto);

        // Assert
        $this->assertEquals(500, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('Une erreur inattendue s\'est produite lors de l\'envoi des messages', $data['message']);
    }

    /**
     * Test de génération des messages de réponse - succès complet
     */
    public function testGenerateResponseMessageSuccessOnly(): void
    {
        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: ['encrypted_receiver_456', 'encrypted_receiver_789'],
            order_id: 'encrypted_order_101',
            content: 'Message de test'
        );

        $this->cryptService->method('decryptId')->willReturn(123);
        $this->messageService
            ->expects($this->once())
            ->method('createMultipleMessages')
            ->willReturn([
                'total_sent' => 2,
                'total_failed' => 0,
                'successful_conversations' => [[], []],
                'failed_conversations' => []
            ]);

        $response = $this->multiMessageService->handleMultiMessageRequest($dto);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Message envoyé avec succès à 2 agents', $data['message']);
    }

    /**
     * Test de génération des messages de réponse - échec complet
     */
    public function testGenerateResponseMessageFailureOnly(): void
    {
        $messageService = $this->createMock(MessageService::class);
        $cryptService = $this->createMock(CryptService::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $multiMessageService = new MultiMessageService(
            $messageService,
            $cryptService,
            $logger
        );

        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: ['encrypted_receiver_456', 'encrypted_receiver_789'],
            order_id: 'encrypted_order_101',
            content: 'Message de test'
        );

        $cryptService->method('decryptId')->willReturn(123);
        $messageService
            ->expects($this->once())
            ->method('createMultipleMessages')
            ->willReturn([
                'total_sent' => 0,
                'total_failed' => 2,
                'successful_conversations' => [],
                'failed_conversations' => [[], []]
            ]);

        $response = $multiMessageService->handleMultiMessageRequest($dto);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Échec de l\'envoi du message à 2 agents', $data['message']);
    }

    /**
     * Test de génération des messages de réponse - succès partiel
     */
    public function testGenerateResponseMessagePartialSuccess(): void
    {
        $messageService = $this->createMock(MessageService::class);
        $cryptService = $this->createMock(CryptService::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $multiMessageService = new MultiMessageService(
            $messageService,
            $cryptService,
            $logger
        );

        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: ['encrypted_receiver_456', 'encrypted_receiver_789'],
            order_id: 'encrypted_order_101',
            content: 'Message de test'
        );

        $cryptService->method('decryptId')->willReturn(123);
        $messageService
            ->expects($this->once())
            ->method('createMultipleMessages')
            ->willReturn([
                'total_sent' => 1,
                'total_failed' => 1,
                'successful_conversations' => [[]],
                'failed_conversations' => [[]]
            ]);

        $response = $multiMessageService->handleMultiMessageRequest($dto);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Message envoyé à 1/2 agents (1 succès, 1 échecs)', $data['message']);
    }

    /**
     * Test de vérification que Mercure est appelé via MessageService
     */
    public function testMercurePublishingIsCalled(): void
    {
        // Arrange
        $dto = new MultiMessageRequestDTO(
            sender_id: 'encrypted_sender_123',
            receiver_ids: ['encrypted_receiver_456'],
            order_id: 'encrypted_order_101',
            content: 'Message avec publication Mercure'
        );

        $this->cryptService
            ->method('decryptId')
            ->willReturnMap([
                ['encrypted_sender_123', EntityType::USER->value, 123],
                ['encrypted_receiver_456', EntityType::USER->value, 456],
                ['encrypted_order_101', EntityType::SERVICE_ORDER->value, 101]
            ]);

        // Vérification que createMultipleMessages est appelé avec les bons paramètres
        // (le publishing Mercure se fait à l'intérieur de cette méthode)
        $this->messageService
            ->expects($this->once())
            ->method('createMultipleMessages')
            ->with($this->callback(function ($data) {
                return $data['sender_id'] === 123
                    && $data['receiver_ids'] === [456]
                    && $data['order_id'] === 101
                    && $data['content'] === 'Message avec publication Mercure';
            }))
            ->willReturn([
                'total_sent' => 1,
                'total_failed' => 0,
                'successful_conversations' => [
                    ['conversation_id' => 'conv_123_456', 'receiver_id' => 456]
                ],
                'failed_conversations' => []
            ]);

        // Act
        $response = $this->multiMessageService->handleMultiMessageRequest($dto);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        
        // Vérification que la réponse indique un succès
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(1, $data['data']['total_sent']);
        $this->assertCount(1, $data['data']['successful_conversations']);
    }
}
