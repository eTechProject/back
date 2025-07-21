<?php

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

class AuthTest extends WebTestCase
{
    public function testLoginReturnsToken(): void
    {
        $client = static::createClient();
        
        // Set up test user if it doesn't exist
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'john@example.com']);
        if (!$user) {
            $user = new User();
            $user->setName('John Doe');
            $user->setEmail('john@example.com');
            $user->setRole(UserRole::CLIENT);
            $user->setPassword($hasher->hashPassword($user, 'password'));
            $em->persist($user);
            $em->flush();
        }

        $client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => 'john@example.com',
            'password' => 'password'
        ]));

        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('token', $data['data']);
        $this->assertIsString($data['data']['token']);
    }
}
