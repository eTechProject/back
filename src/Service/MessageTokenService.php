<?php

namespace App\Service;

use App\Entity\User;
use App\Service\MercureTokenGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;

class MessageTokenService
{
    public function __construct(
        private MercureTokenGenerator $mercureTokenGenerator
    ) {}

    public function generateTokenResponse(?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse([
                'error' => 'Utilisateur non authentifié',
                'status' => 'error'
            ], 401);
        }
        
        try {
            $result = $this->mercureTokenGenerator->generateTokenForUser($user);
            
            return new JsonResponse([
                'mercureToken' => $result['token'],
                'topics' => $result['topics'],
                'expires_in' => $result['expires_in'],
                'status' => 'success'
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la génération du token Mercure: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
}
