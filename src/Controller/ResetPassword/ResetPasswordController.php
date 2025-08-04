<?php

namespace App\Controller\ResetPassword;

use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public/reset-password', name: 'api_reset_password', methods: ['POST'])]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Format JSON invalide',
                'data' => null
            ], 400);
        }

        $token = $data['token'] ?? null;
        $newPassword = $data['password'] ?? null;

        if (!$token || !$newPassword) {
            return $this->json([
                'success' => false,
                'message' => 'Le token et le nouveau mot de passe sont requis.',
                'data' => null
            ], 400);
        }

        try {
            $result = $this->passwordResetService->resetPassword($token, $newPassword);

            if ($result !== true) {
                return $this->json([
                    'success' => false,
                    'message' => $result,
                    'data' => null
                ], 400);
            }

            return $this->json([
                'success' => true,
                'message' => 'Le mot de passe a été réinitialisé avec succès.',
                'data' => null
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'data' => null
            ], 500);
        }
    }
}
