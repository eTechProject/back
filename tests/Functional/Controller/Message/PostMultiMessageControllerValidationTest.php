<?php

namespace Tests\Functional\Controller\Message;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use App\Entity\ServiceOrders;
use App\Enum\UserRole;
use App\Enum\ServiceOrderStatus;
use App\Service\CryptService;
use App\Enum\EntityType;
use Doctrine\ORM\EntityManagerInterface;

class PostMultiMessageControllerValidationTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private CryptService $cryptService;

    protected function setUp(): void
    {
        $kernel = static::createKernel();
        $kernel->boot();
        $container = $kernel->getContainer();
        
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->cryptService = $container->get(CryptService::class);
    }

    /**
     * Test de validation des champs requis
     */
    public function testValidationRequiredFields(): void
    {
        $client = static::createClient();

        // Test avec tous les champs vides
        $client->request('POST', '/api/messages/multi', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'sender_id' => '',
            'receiver_ids' => [],
            'order_id' => '',
            'content' => ''
        ]));

        $this->assertEquals(422, $client->getResponse()->getStatusCode());
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertArrayHasKey('violations', $responseData);
    }

    /**
     * Test de validation du contenu trop long
     */
    public function testValidationContentTooLong(): void
    {
        $client = static::createClient();
        $longContent = str_repeat('a', 1001); // 1001 caractères

        $client->request('POST', '/api/messages/multi', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'sender_id' => 'test_sender',
            'receiver_ids' => ['test_receiver'],
            'order_id' => 'test_order',
            'content' => $longContent
        ]));

        $this->assertEquals(422, $client->getResponse()->getStatusCode());
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertArrayHasKey('violations', $responseData);
        
        // Vérifier qu'il y a une violation sur le contenu
        $violations = $responseData['violations'];
        $contentViolationFound = false;
        foreach ($violations as $violation) {
            if ($violation['property_path'] === 'content') {
                $contentViolationFound = true;
                break;
            }
        }
        $this->assertTrue($contentViolationFound, 'Violation de longueur de contenu attendue');
    }

    /**
     * Test de validation avec tableau receiver_ids vide
     */
    public function testValidationEmptyReceiverIds(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/messages/multi', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'sender_id' => 'test_sender',
            'receiver_ids' => [],
            'order_id' => 'test_order',
            'content' => 'Test message'
        ]));

        $this->assertEquals(422, $client->getResponse()->getStatusCode());
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertArrayHasKey('violations', $responseData);
    }

    /**
     * Test de validation avec données JSON malformées
     */
    public function testValidationMalformedJson(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/messages/multi', [], [], ['CONTENT_TYPE' => 'application/json'], '{"sender_id": "test", invalid_json}');

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertStringContains('JSON', $responseData['message']);
    }

    /**
     * Test d'accès non autorisé sans token JWT
     */
    public function testUnauthorizedAccessWithoutToken(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/messages/multi', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'sender_id' => 'test_sender',
            'receiver_ids' => ['test_receiver'],
            'order_id' => 'test_order',
            'content' => 'Test message'
        ]));

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    /**
     * Test de validation avec IDs chiffrés invalides
     */
    public function testValidationInvalidEncryptedIds(): void
    {
        // Créer un client avec token JWT valide (simulé)
        $client = static::createClient();
        
        // Note: Ce test nécessiterait un token JWT valide pour passer l'authentification
        // et atteindre la validation des IDs chiffrés dans MultiMessageService
        $this->markTestSkipped('Nécessite configuration JWT pour test complet');
    }

    /**
     * Test de validation du format JSON
     */
    public function testValidationJsonFormat(): void
    {
        $client = static::createClient();

        // Test avec Content-Type incorrect
        $client->request('POST', '/api/messages/multi', [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode([
            'sender_id' => 'test_sender',
            'receiver_ids' => ['test_receiver'],
            'order_id' => 'test_order',
            'content' => 'Test message'
        ]));

        // Devrait retourner une erreur de format
        $this->assertNotEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * Test de validation avec receiver_ids non-array
     */
    public function testValidationReceiverIdsNotArray(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/messages/multi', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'sender_id' => 'test_sender',
            'receiver_ids' => 'not_an_array', // Devrait être un array
            'order_id' => 'test_order',
            'content' => 'Test message'
        ]));

        $this->assertEquals(422, $client->getResponse()->getStatusCode());
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('error', $responseData['status']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
