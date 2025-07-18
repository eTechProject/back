<?php

namespace App\Controller;

use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public', name: 'api_')]
class ResetPasswordController extends AbstractController
{
    public function __construct(private PasswordResetService $passwordResetService) {}

    #[Route('/request-reset-password', name: 'forgot_password_request', methods: ['POST'])]
    public function request(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json([
                'success' => false,
                'message' => 'Email is required.',
                'data' => null
            ], 400);
        }

        try {
            $this->passwordResetService->handleResetRequest($email);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }

        return $this->json([
            'success' => true,
            'message' => 'If your email exists, you will receive a reset link.',
            'data' => null
        ]);
    }

    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;
        $newPassword = $data['password'] ?? null;

        if (!$token || !$newPassword) {
            return $this->json([
                'success' => false,
                'message' => 'Le token et le nouveau mot de passe sont requis.',
                'data' => null
            ], 400);
        }

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
    }
}
