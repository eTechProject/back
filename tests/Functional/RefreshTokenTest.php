<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Service\RefreshTokenManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class RefreshTokenTest extends WebTestCase
{
    private $client;
    private $em;
    private $refreshTokenManager;
    private $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->refreshTokenManager = $container->get(RefreshTokenManager::class);
        $this->user = $this->em->getRepository(User::class)->findOneBy([]);
        if (!$this->user) {
            $this->user = new User();
            $this->user->setEmail('test@example.com');
            $this->user->setPassword('password');
            $this->em->persist($this->user);
            $this->em->flush();
        }
    }

    public function testRefreshWithValidToken(): void
    {
        $refreshToken = $this->refreshTokenManager->generate($this->user);
        $this->client->request('POST', '/api/token/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => $refreshToken->plainToken,
        ]));
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
    }

    public function testRefreshWithExpiredOrRevokedToken(): void
    {
        $refreshToken = $this->refreshTokenManager->generate($this->user);
        $this->refreshTokenManager->revoke($refreshToken);
        $this->client->request('POST', '/api/token/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => $refreshToken->plainToken,
        ]));
        $this->assertResponseStatusCodeSame(401);
    }

    public function testLogoutRevokesToken(): void
    {
        $refreshToken = $this->refreshTokenManager->generate($this->user);
        $this->client->request('POST', '/api/logout', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => $refreshToken->plainToken,
        ]));
        $this->assertResponseStatusCodeSame(204);
        // Try to use the same token again
        $this->client->request('POST', '/api/token/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => $refreshToken->plainToken,
        ]));
        $this->assertResponseStatusCodeSame(401);
    }
}
