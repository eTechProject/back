<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'message' => 'Service is healthy',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    #[Route('/', name: 'root', methods: ['GET'])]
    public function root(): JsonResponse
    {
        return $this->json([
            'message' => 'API is running',
            'version' => '1.0.0'
        ]);
    }
}
