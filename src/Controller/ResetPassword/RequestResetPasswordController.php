<?php

namespace App\Controller\ResetPassword;

use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public/request-reset-password', name: 'api_forgot_password_request', methods: ['POST'])]
class RequestResetPasswordController extends AbstractController
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
            
            return $this->json([
                'success' => true,
                'message' => 'If your email exists, you will receive a reset link.',
                'data' => null
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }
}
