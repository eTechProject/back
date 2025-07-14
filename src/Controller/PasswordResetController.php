<?php 
namespace App\Controller;

use App\Service\PasswordResetService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class PasswordResetController
{
    public function __construct(private PasswordResetService $resetService) {}

    #[Route('/request-reset', name: 'api_request_reset', methods: ['POST'])]
    public function requestReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->resetService->handleResetRequest($data['email']);

        return new JsonResponse(['message' => 'Si l’email existe, un lien a été envoyé']);
    }

    #[Route('/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->resetService->reset($data['token'], $data['newPassword']);

        return new JsonResponse(['message' => 'Mot de passe réinitialisé avec succès']);
    }
}

