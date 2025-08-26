<?php
namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

class RefreshTokenManager
{
    private $em;
    private $repository;
    private $ttl;

    public function __construct(EntityManagerInterface $em, RefreshTokenRepository $repository, int $ttl = 2592000)
    {
        $this->em = $em;
        $this->repository = $repository;
        $this->ttl = $ttl;
    }

    public function generate(User $user): RefreshToken
    {
        $plainToken = bin2hex(random_bytes(64));
        $hashedToken = password_hash($plainToken, PASSWORD_DEFAULT);
        $refreshToken = new RefreshToken();
        $refreshToken->setToken($hashedToken)
            ->setUser($user)
            ->setExpiresAt((new \DateTimeImmutable())->modify("+{$this->ttl} seconds"))
            ->setRevoked(false);
        $this->em->persist($refreshToken);
        $this->em->flush();
        // Attach plainToken for controller access
        $refreshToken->setPlainToken($plainToken);
        return $refreshToken;
    }

    public function validate(string $plainToken, User $user): ?RefreshToken
    {
        $tokens = $this->repository->findBy(['user' => $user, 'revoked' => false]);
        foreach ($tokens as $token) {
            if (password_verify($plainToken, $token->getToken())) {
                if ($token->getExpiresAt() < new \DateTimeImmutable() || $token->isRevoked()) {
                    return null;
                }
                return $token;
            }
        }
        return null;
    }

    public function revoke(RefreshToken $token): void
    {
        $token->setRevoked(true);
        $this->em->flush();
    }

    public function revokeByPlainToken(string $plainToken, User $user): void
    {
        $token = $this->validate($plainToken, $user);
        if ($token) {
            $this->revoke($token);
        }
    }
    public function revokeByPlainTokenGlobal(string $plainToken): bool
    {
        $tokens = $this->repository->findBy(['revoked' => false]);
        foreach ($tokens as $token) {
            if (password_verify($plainToken, $token->getToken())) {
                if ($token->getExpiresAt() < new \DateTimeImmutable() || $token->isRevoked()) {
                    return false;
                }
                $this->revoke($token);
                return true;
            }
        }
        return false;
    }
    public function findValidRefreshToken(string $plainToken): ?RefreshToken
    {
        $tokens = $this->repository->findBy(['revoked' => false]);
        foreach ($tokens as $token) {
            if (password_verify($plainToken, $token->getToken())) {
                if ($token->getExpiresAt() < new \DateTimeImmutable() || $token->isRevoked()) {
                    return null;
                }
                return $token;
            }
        }
        return null;
    }
}
