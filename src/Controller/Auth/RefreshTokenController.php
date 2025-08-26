<?php

namespace App\Controller\Auth;

use App\Dto\User\Request\RefreshTokenRequest;
use App\Service\RefreshTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
class RefreshTokenController extends AbstractController
{
    public function __construct(
        private readonly RefreshTokenManager $refreshTokenManager,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                RefreshTokenRequest::class,
                'json'
            );
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Le format de la requête est invalide',
                'errors' => ['Invalid JSON format']
            ], 400);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json([
                'status' => 'error',
                'message' => 'Token de rafraîchissement manquant ou invalide',
                'errors' => (string) $errors
            ], 400);
        }

        $refreshToken = $this->refreshTokenManager->findValidRefreshToken($dto->refresh_token);
        if (!$refreshToken) {
            return $this->json([
                'status' => 'error',
                'message' => 'Token de rafraîchissement invalide ou expiré'
            ], 401);
        }
        // Rotate token
        $this->refreshTokenManager->revoke($refreshToken);
        $newToken = $this->refreshTokenManager->generate($refreshToken->getUser());
        $accessToken = $this->jwtManager->create($refreshToken->getUser());
        $decodedToken = $this->jwtManager->parse($accessToken);
        $exp = $decodedToken['exp'] ?? null;
        return $this->json([
            'status' => 'success',
            'token' => $accessToken,
            'expires_at' => $exp,
            'refresh_token' => $newToken->getPlainToken(),
        ]);
    }
}
