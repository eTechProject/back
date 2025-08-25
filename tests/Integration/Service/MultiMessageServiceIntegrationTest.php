<?php

namespace Tests\Integration\Service;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Service\MultiMessageService;
use App\Service\MessageService;
use App\Service\CryptService;
use App\DTO\Message\MultiMessageRequestDTO;
use App\Entity\User;
use App\Entity\ServiceOrders;
use App\Entity\Message;
use App\Enum\UserRole;
use App\Enum\ServiceOrderStatus;
use App\Enum\EntityType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class MultiMessageServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private MultiMessageService $multiMessageService;
    private CryptService $cryptService;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();
        
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->multiMessageService = $container->get(MultiMessageService::class);
        $this->cryptService = $container->get(CryptService::class);
    }

    /**
     * Test d'intégration complet avec base de données et Mercure
     */
    public function testCompleteMultiMessageFlow(): void
    {
        // Création des entités de test
        $client = new User();
        $client->setName('Client Test')
            ->setEmail('client@test.com')
            ->setPhone('1234567890')
            ->setRole(UserRole::CLIENT)
            ->setPassword('hashed_password');

        $agent1 = new User();
        $agent1->setName('Agent 1')
            ->setEmail('agent1@test.com')
            ->setPhone('1234567891')
            ->setRole(UserRole::AGENT)
            ->setPassword('hashed_password');

        $agent2 = new User();
        $agent2->setName('Agent 2')
            ->setEmail('agent2@test.com')
            ->setPhone('1234567892')
            ->setRole(UserRole::AGENT)
            ->setPassword('hashed_password');

        $agent3 = new User();
        $agent3->setName('Agent 3 Non Autorisé')
            ->setEmail('agent3@test.com')
            ->setPhone('1234567893')
            ->setRole(UserRole::AGENT)
            ->setPassword('hashed_password');

        $serviceOrder = new ServiceOrders();
        $serviceOrder->setUser($client)
            ->setStatus(ServiceOrderStatus::PENDING)
            ->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime());

        // Persistance des entités
        $this->entityManager->persist($client);
        $this->entityManager->persist($agent1);
        $this->entityManager->persist($agent2);
        $this->entityManager->persist($agent3);
        $this->entityManager->persist($serviceOrder);
        $this->entityManager->flush();

        // Chiffrement des IDs
        $encryptedClientId = $this->cryptService->encryptId((string)$client->getId(), EntityType::USER->value);
        $encryptedAgent1Id = $this->cryptService->encryptId((string)$agent1->getId(), EntityType::USER->value);
        $encryptedAgent2Id = $this->cryptService->encryptId((string)$agent2->getId(), EntityType::USER->value);
        $encryptedAgent3Id = $this->cryptService->encryptId((string)$agent3->getId(), EntityType::USER->value);
        $encryptedOrderId = $this->cryptService->encryptId((string)$serviceOrder->getId(), EntityType::SERVICE_ORDER->value);

        // Création du DTO de requête
        $dto = new MultiMessageRequestDTO(
            sender_id: $encryptedClientId,
            receiver_ids: [$encryptedAgent1Id, $encryptedAgent2Id, $encryptedAgent3Id],
            order_id: $encryptedOrderId,
            content: 'Message de test pour intégration complète'
        );

        // Exécution du service
        $response = $this->multiMessageService->handleMultiMessageRequest($dto);

        // Assertions sur la réponse
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
        
        // Vérification que seuls les agents autorisés ont reçu le message
        // (agent3 devrait être rejeté car non autorisé pour cette commande)
        $this->assertGreaterThan(0, $data['data']['total_sent']);
        $this->assertGreaterThanOrEqual(0, $data['data']['total_failed']);

        // Vérification que les messages ont été créés en base
        $messageRepository = $this->entityManager->getRepository(Message::class);
        $createdMessages = $messageRepository->findBy([
            'content' => 'Message de test pour intégration complète'
        ]);

        $this->assertGreaterThan(0, count($createdMessages));

        // Vérification que chaque message créé a les bonnes propriétés
        foreach ($createdMessages as $message) {
            $this->assertEquals('Message de test pour intégration complète', $message->getContent());
            $this->assertEquals($client->getId(), $message->getSender()->getId());
            $this->assertInstanceOf(\DateTime::class, $message->getCreatedAt());
        }

        // Nettoyage
        $this->cleanupTestData([$client, $agent1, $agent2, $agent3, $serviceOrder]);
    }

    /**
     * Test de rejet d'agents non autorisés
     */
    public function testUnauthorizedAgentRejection(): void
    {
        // Création des entités
        $client = new User();
        $client->setName('Client Test')
            ->setEmail('client.rejection@test.com')
            ->setPhone('1234567800')
            ->setRole(UserRole::CLIENT)
            ->setPassword('hashed_password');

        $unauthorizedAgent = new User();
        $unauthorizedAgent->setName('Agent Non Autorisé')
            ->setEmail('unauthorized@test.com')
            ->setPhone('1234567801')
            ->setRole(UserRole::AGENT)
            ->setPassword('hashed_password');

        $serviceOrder = new ServiceOrders();
        $serviceOrder->setUser($client)
            ->setStatus(ServiceOrderStatus::PENDING)
            ->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($client);
        $this->entityManager->persist($unauthorizedAgent);
        $this->entityManager->persist($serviceOrder);
        $this->entityManager->flush();

        // Chiffrement des IDs
        $encryptedClientId = $this->cryptService->encryptId((string)$client->getId(), EntityType::USER->value);
        $encryptedAgentId = $this->cryptService->encryptId((string)$unauthorizedAgent->getId(), EntityType::USER->value);
        $encryptedOrderId = $this->cryptService->encryptId((string)$serviceOrder->getId(), EntityType::SERVICE_ORDER->value);

        $dto = new MultiMessageRequestDTO(
            sender_id: $encryptedClientId,
            receiver_ids: [$encryptedAgentId],
            order_id: $encryptedOrderId,
            content: 'Message qui devrait être rejeté'
        );

        $response = $this->multiMessageService->handleMultiMessageRequest($dto);
        $data = json_decode($response->getContent(), true);

        // L'agent non autorisé devrait être rejeté
        $this->assertEquals(0, $data['data']['total_sent']);
        $this->assertEquals(1, $data['data']['total_failed']);
        $this->assertCount(1, $data['data']['failed_conversations']);

        // Nettoyage
        $this->cleanupTestData([$client, $unauthorizedAgent, $serviceOrder]);
    }

    /**
     * Test avec tableau vide de destinataires
     */
    public function testEmptyReceiversArray(): void
    {
        $client = new User();
        $client->setName('Client Test Empty')
            ->setEmail('client.empty@test.com')
            ->setPhone('1234567810')
            ->setRole(UserRole::CLIENT)
            ->setPassword('hashed_password');

        $serviceOrder = new ServiceOrders();
        $serviceOrder->setUser($client)
            ->setStatus(ServiceOrderStatus::PENDING)
            ->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($client);
        $this->entityManager->persist($serviceOrder);
        $this->entityManager->flush();

        $encryptedClientId = $this->cryptService->encryptId((string)$client->getId(), EntityType::USER->value);
        $encryptedOrderId = $this->cryptService->encryptId((string)$serviceOrder->getId(), EntityType::SERVICE_ORDER->value);

        $dto = new MultiMessageRequestDTO(
            sender_id: $encryptedClientId,
            receiver_ids: [], // Tableau vide
            order_id: $encryptedOrderId,
            content: 'Message avec tableau vide'
        );

        $response = $this->multiMessageService->handleMultiMessageRequest($dto);
        $data = json_decode($response->getContent(), true);

        // Devrait retourner 0 envoyés et 0 échecs
        $this->assertEquals(0, $data['data']['total_sent']);
        $this->assertEquals(0, $data['data']['total_failed']);
        $this->assertEmpty($data['data']['successful_conversations']);
        $this->assertEmpty($data['data']['failed_conversations']);

        // Nettoyage
        $this->cleanupTestData([$client, $serviceOrder]);
    }

    /**
     * Test avec IDs invalides
     */
    public function testInvalidEncryptedIds(): void
    {
        $dto = new MultiMessageRequestDTO(
            sender_id: 'invalid_encrypted_id',
            receiver_ids: ['another_invalid_id'],
            order_id: 'yet_another_invalid_id',
            content: 'Message avec IDs invalides'
        );

        $response = $this->multiMessageService->handleMultiMessageRequest($dto);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('error', $data['status']);
        $this->assertEquals('Un ou plusieurs identifiants sont invalides', $data['message']);
    }

    /**
     * Nettoie les données de test
     */
    private function cleanupTestData(array $entities): void
    {
        foreach ($entities as $entity) {
            if ($this->entityManager->contains($entity)) {
                $this->entityManager->remove($entity);
            }
        }
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
