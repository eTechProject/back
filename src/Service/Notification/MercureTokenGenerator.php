<?php

namespace App\Service\Notification;

use App\Service\CryptService;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Psr\Log\LoggerInterface;

class MercureTokenGenerator
{
    public function __construct(
        private readonly string $mercureJwtSecret,
        private readonly CryptService $cryptService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Génère un JWT Mercure pour un utilisateur avec les topics de souscription
     */
    public function generateUserToken(int $userId): string
    {
        try {
            $encryptedUserId = $this->cryptService->encryptId($userId, 'user');
            
            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText($this->mercureJwtSecret)
            );

            $now = new \DateTimeImmutable();
            $token = $config->builder()
                ->issuedBy('notification-system')
                ->permittedFor('mercure-hub')
                ->issuedAt($now)
                ->expiresAt($now->modify('+1 hour'))
                ->withClaim('mercure', [
                    'subscribe' => [
                        "/users/{$encryptedUserId}/notifications",
                        "/notifications/all"
                    ]
                ])
                ->getToken($config->signer(), $config->signingKey());

            $this->logger->info('Mercure token generated for user', [
                'user_id' => $userId,
                'encrypted_user_id' => $encryptedUserId,
                'expires_at' => $now->modify('+1 hour')->format('c')
            ]);

            return $token->toString();

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate Mercure token', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Impossible de générer le token Mercure', 0, $e);
        }
    }

    /**
     * Génère un JWT Mercure pour un administrateur avec accès étendu
     */
    public function generateAdminToken(int $adminId): string
    {
        try {
            $encryptedAdminId = $this->cryptService->encryptId($adminId, 'user');
            
            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText($this->mercureJwtSecret)
            );

            $now = new \DateTimeImmutable();
            $token = $config->builder()
                ->issuedBy('notification-system')
                ->permittedFor('mercure-hub')
                ->issuedAt($now)
                ->expiresAt($now->modify('+2 hours'))
                ->withClaim('mercure', [
                    'subscribe' => [
                        "/users/{$encryptedAdminId}/notifications",
                        "/notifications/all",
                        "/notifications/admin",
                        "/notifications/stats"
                    ]
                ])
                ->getToken($config->signer(), $config->signingKey());

            return $token->toString();

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate admin Mercure token', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Impossible de générer le token Mercure admin', 0, $e);
        }
    }
}
