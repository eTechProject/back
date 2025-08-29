<?php

namespace App\Controller\Auth;

use App\DTO\User\Request\RefreshTokenRequest;
use App\Service\RefreshTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
class LogoutController extends AbstractController
{
    public function __construct(
        private readonly RefreshTokenManager $refreshTokenManager,
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

        $revoked = $this->refreshTokenManager->revokeByPlainTokenGlobal($dto->refresh_token);
        if ($revoked) {
            return $this->json(null, 204);
        }
        return $this->json([
            'status' => 'error',
            'message' => 'Token de rafraîchissement invalide ou déjà révoqué'
        ], 401);
    }
}
