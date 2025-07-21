<?php

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class UserRegistrationTest extends WebTestCase
{
    private string $baseUrl = '/api/register';
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        
        // Clear the test database before each test
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $em->getConnection();
        $connection->executeStatement('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE reset_password_request RESTART IDENTITY CASCADE');
    }

    private function post(array $payload): void
    {        
        $this->client->request(
            'POST',
            $this->baseUrl,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload)
        );
    }

    public function testRegisterUserSuccessfully(): void
    {
        $this->post([
            'name' => 'John Doe',
            'email' => 'john_' . uniqid() . '@example.com',
            'phone' => '+261341234567',
            'role' => 'client',
            'password' => 'StrongPassword123!',
        ]);

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('Utilisateur enregistré avec succès', $data['message']);
    }

    public function testRegisterFailsWithMissingEmail(): void
    {
        $this->post([
            'name' => 'No Email',
            'password' => 'StrongPassword123!',
            'role' => 'client',
        ]);

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertArrayHasKey('email', $data['errors']);
    }

    public function testRegisterFailsWithInvalidEmail(): void
    {
        $this->post([
            'name' => 'Invalid Email',
            'email' => 'not-an-email',
            'password' => 'StrongPassword123!',
            'role' => 'client',
        ]);

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertArrayHasKey('email', $data['errors']);
    }

    public function testRegisterFailsWithDuplicateEmail(): void
    {
        $email = 'duplicate@example.com';

        // First registration
        $this->post([
            'name' => 'First User',
            'email' => $email,
            'password' => 'Password123!',
            'role' => 'client',
        ]);
        $firstResponse = $this->client->getResponse();
        $this->assertSame(Response::HTTP_CREATED, $firstResponse->getStatusCode());

        // Second registration with same email
        $this->post([
            'name' => 'Second User',
            'email' => $email,
            'password' => 'Password123!',
            'role' => 'client',
        ]);
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertSame('Email already exists', $data['message']);
    }

    public function testRegisterFailsWithInvalidJson(): void
    {        
        $this->client->request(
            'POST',
            $this->baseUrl,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"name": "Invalid JSON",' // Missing closing }
        );

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertSame('Le format de la requête est invalide', $data['message']);
    }
}
