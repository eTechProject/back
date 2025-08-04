<?php

namespace App\Controller\Message;

use App\Service\MessageTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/messages/mercure-token', name: 'api_messages_mercure_token', methods: ['GET'])]
class GenerateMercureTokenController extends AbstractController
{
    public function __construct(
        private readonly MessageTokenService $tokenService
    ) {}

    public function __invoke(): JsonResponse
    {
        try {
            return $this->tokenService->generateTokenResponse($this->getUser());
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la génération du token Mercure'
            ], 500);
        }
    }
}
