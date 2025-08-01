<?php
namespace App\EventListener;

use App\DTO\User\Internal\UserDTO;
use App\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class JwtLoginSuccessHandler
{
    public function __construct(
        private UserService $userService,
        private SerializerInterface $serializer,
        private JWTTokenManagerInterface $jwtManager,
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

        $data = [
            'token' => $token,
            'expires_at' => $exp,
            'user' => json_decode($this->serializer->serialize($userDto, 'json'), true),
        ];

        $event->setData([
            'status' => 200,
            'message' => 'Connecté avec succès',
            'data' => $data,
        ]);
    }
}
