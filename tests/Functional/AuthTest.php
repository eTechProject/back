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
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'john@example.com']);
        if (!$user) {
            $user = new User();
            $user->setName('John Doe');
            $user->setEmail('john@example.com');
            $user->setRole(UserRole::CLIENT);
            $user->setPassword($this->hasher->hashPassword($user, 'password'));
            $this->em->persist($user);
            $this->em->flush();
        }
    }

    public function testLoginReturnsToken(): void
    {
        $client = static::createClient();

        $client->request('POST', '/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => 'john@example.com',
            'password' => 'password'
        ]));

        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertIsString($data['token']);
    }
}
