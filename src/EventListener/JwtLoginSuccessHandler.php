<?php
namespace App\EventListener;

use App\DTO\User\Internal\UserDTO;
use App\Service\UserService;
use App\Service\RefreshTokenManager;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class JwtLoginSuccessHandler
{
    public function __construct(
        private UserService $userService,
        private SerializerInterface $serializer,
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenManager $refreshTokenManager,
    ) {}

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user) {
            return;
        }

        $userDto = $this->userService->toDTO($user);

        $token = $event->getData()['token'] ?? null;
        $decodedToken = $this->jwtManager->parse($token);
        $exp = $decodedToken['exp'] ?? null;

        // Generate refresh token (long expiry, e.g. 30 days)
        $refreshToken = $this->refreshTokenManager->generate($user);

        $data = [
            'token' => $token,
            'expires_at' => $exp,
            'refresh_token' => $refreshToken->getPlainToken(),
            'refresh_token_expires_at' => $refreshToken->getExpiresAt()?->getTimestamp(),
            'user' => json_decode($this->serializer->serialize($userDto, 'json'), true),
        ];

        $event->setData([
            'status' => 200,
            'message' => 'Connecté avec succès',
            'data' => $data,
        ]);
    }
}
