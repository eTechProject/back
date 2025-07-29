<?php

namespace Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\User;
use App\Entity\Agents;
use App\Enum\UserRole;
use App\Enum\Genre;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class ClientControllerTest extends WebTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?JWTTokenManagerInterface $jwtManager = null;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = static::getContainer();
        
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->jwtManager = $container->get(JWTTokenManagerInterface::class);
        
        // Clear any existing data
        $this->entityManager->createQuery('DELETE FROM App\Entity\Tasks')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Agents')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testGetAvailableAgentsEndpointWithAuthentication(): void
    {
        $client = static::createClient();
        
        // Create a test user
        $user = new User();
        $user->setEmail('test@example.com')
             ->setName('Test User')
             ->setPhone('1234567890')
             ->setRole(UserRole::CLIENT)
             ->setPassword('hashed_password');
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        // Generate JWT token
        $token = $this->jwtManager->create($user);
        
        // Make authenticated request
        $client->request('GET', '/api/client/available-agents', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ]);
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('status', $responseData);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('success', $responseData['status']);
        $this->assertIsArray($responseData['data']);
    }

    public function testGetAvailableAgentsWithAvailableAgent(): void
    {
        $client = static::createClient();
        
        // Create a test client user
        $clientUser = new User();
        $clientUser->setEmail('client@example.com')
                   ->setName('Client User')
                   ->setPhone('1234567890')
                   ->setRole(UserRole::CLIENT)
                   ->setPassword('hashed_password');
        
        $this->entityManager->persist($clientUser);
        
        // Create a test agent user
        $agentUser = new User();
        $agentUser->setEmail('agent@example.com')
                  ->setName('Agent User')
                  ->setPhone('0987654321')
                  ->setRole(UserRole::AGENT)
                  ->setPassword('hashed_password');
        
        $this->entityManager->persist($agentUser);
        
        // Create an agent entity
        $agent = new Agents();
        $agent->setUser($agentUser)
              ->setAddress('123 Test Street')
              ->setSexe(Genre::M)
              ->setProfilePictureUrl('https://example.com/pic.jpg');
        
        $this->entityManager->persist($agent);
        $this->entityManager->flush();
        
        // Generate JWT token for client
        $token = $this->jwtManager->create($clientUser);
        
        // Make authenticated request
        $client->request('GET', '/api/client/available-agents', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ]);
        
        $this->assertResponseIsSuccessful();
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertEquals('success', $responseData['status']);
        $this->assertCount(1, $responseData['data']);
        
        $agentData = $responseData['data'][0];
        $this->assertArrayHasKey('encryptedId', $agentData);
        $this->assertArrayHasKey('address', $agentData);
        $this->assertArrayHasKey('sexe', $agentData);
        $this->assertArrayHasKey('profilePictureUrl', $agentData);
        $this->assertArrayHasKey('user', $agentData);
        
        // Verify data
        $this->assertEquals('123 Test Street', $agentData['address']);
        $this->assertEquals('M', $agentData['sexe']);
        $this->assertEquals('https://example.com/pic.jpg', $agentData['profilePictureUrl']);
        
        // Check user data
        $userData = $agentData['user'];
        $this->assertArrayHasKey('name', $userData);
        $this->assertArrayHasKey('email', $userData);
        $this->assertEquals('Agent User', $userData['name']);
        $this->assertEquals('agent@example.com', $userData['email']);
    }

    public function testGetAvailableAgentsUnauthorized(): void
    {
        $client = static::createClient();
        
        // Make request without authentication
        $client->request('GET', '/api/client/available-agents');
        
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(401, $responseData['code']);
        $this->assertStringContainsString('JWT Token not found', $responseData['message']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        if ($this->entityManager) {
            // Clean up test data
            $this->entityManager->createQuery('DELETE FROM App\Entity\Tasks')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\Agents')->execute(); 
            $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
            
            $this->entityManager->close();
            $this->entityManager = null;
        }
    }
}
