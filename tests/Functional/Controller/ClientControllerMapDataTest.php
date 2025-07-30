<?php

namespace Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class ClientControllerMapDataTest extends WebTestCase
{
    public function testGetClientMapDataEndpointRequiresAuthentication(): void
    {
        $client = static::createClient();
        
        // Make a request without authentication
        $client->request('GET', '/api/client/map-data');
        
        // Should return 401 Unauthorized
        $this->assertResponseStatusCodeSame(401);
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertStringContainsString('JWT Token not found', $responseData['message']);
    }

    public function testGetClientMapDataEndpointWithAuthentication(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        
        $entityManager = $container->get(EntityManagerInterface::class);
        $jwtManager = $container->get(JWTTokenManagerInterface::class);
        
        // Clean up any existing test data
        $entityManager->createQuery('DELETE FROM App\Entity\User u WHERE u.email LIKE \'%test%\'')->execute();
        
        // Create a test user
        $user = new User();
        $user->setEmail('maptest@example.com')
             ->setName('Map Test User')
             ->setPhone('1234567890')
             ->setRole(UserRole::CLIENT)
             ->setPassword('hashed_password');
        
        $entityManager->persist($user);
        $entityManager->flush();
        
        // Generate JWT token
        $token = $jwtManager->create($user);
        
        // Make authenticated request
        $client->request('GET', '/api/client/map-data', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ]);
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        // Verify response structure
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('timestamp', $responseData);
        
        $this->assertTrue($responseData['success']);
        $this->assertIsArray($responseData['data']);
        $this->assertEquals('Données de la carte client récupérées avec succès', $responseData['message']);
        
        // Since there are no service orders in the test database, data should be empty
        $this->assertEmpty($responseData['data']);
        
        // Clean up test data
        $entityManager->remove($user);
        $entityManager->flush();
    }
}
