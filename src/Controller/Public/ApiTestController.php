<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public', name: 'api_public_')]
class ApiTestController extends AbstractController
{
    /**
     * Endpoint de santé de l'API - Aucune authentification requise
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'success',
            'message' => 'API is healthy and running',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'version' => '1.0.0',
            'environment' => $_ENV['APP_ENV'] ?? 'unknown'
        ]);
    }

    /**
     * Informations sur l'API - Aucune authentification requise
     */
    #[Route('/info', name: 'info', methods: ['GET'])]
    public function info(): JsonResponse
    {
        return $this->json([
            'status' => 'success',
            'data' => [
                'api_name' => 'eTech Agent Location API',
                'version' => '1.0.0',
                'description' => 'API pour la gestion des agents et des localisations',
                'environment' => $_ENV['APP_ENV'] ?? 'unknown',
                'php_version' => PHP_VERSION,
                'framework' => 'Symfony 6.x',
                'available_endpoints' => [
                    'health' => 'GET /api/public/health - Vérification de la santé de l\'API',
                    'info' => 'GET /api/public/info - Informations sur l\'API',
                    'test' => 'GET /api/public/test - Endpoint de test simple',
                    'echo' => 'POST /api/public/echo - Echo des données envoyées'
                ]
            ],
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ]);
    }

    /**
     * Endpoint de test simple - Aucune authentification requise
     */
    #[Route('/test', name: 'test', methods: ['GET'])]
    public function test(): JsonResponse
    {
        return $this->json([
            'status' => 'success',
            'message' => 'Test endpoint is working correctly',
            'data' => [
                'server_time' => (new \DateTimeImmutable())->format('c'),
                'random_number' => random_int(1, 1000),
                'test_data' => [
                    'sample_agent' => [
                        'id' => 'encrypted_agent_123',
                        'name' => 'John Doe',
                        'status' => 'active'
                    ],
                    'sample_location' => [
                        'longitude' => 2.3522,
                        'latitude' => 48.8566,
                        'accuracy' => 10.0,
                        'timestamp' => (new \DateTimeImmutable())->format('c')
                    ]
                ]
            ]
        ]);
    }

    /**
     * Endpoint echo pour tester les requêtes POST - Aucune authentification requise
     */
    #[Route('/echo', name: 'echo', methods: ['POST'])]
    public function echo(\Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $content = $request->getContent();
        $jsonData = null;
        
        if ($content) {
            try {
                $jsonData = json_decode($content, true);
            } catch (\Exception $e) {
                $jsonData = null;
            }
        }

        return $this->json([
            'status' => 'success',
            'message' => 'Echo endpoint - your data returned',
            'received_data' => $jsonData,
            'raw_content' => $content,
            'headers' => [
                'content_type' => $request->headers->get('Content-Type'),
                'user_agent' => $request->headers->get('User-Agent'),
                'accept' => $request->headers->get('Accept')
            ],
            'method' => $request->getMethod(),
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ]);
    }
}
