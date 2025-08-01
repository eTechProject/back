<?php

namespace App\Controller\Client;

use App\Service\AgentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/client/available-agents', name: 'api_client_available_agents', methods: ['GET'])]
class GetAvailableAgentsController extends AbstractController
{
    public function __construct(
        private readonly AgentService $agentService
    ) {}

    public function __invoke(): JsonResponse
    {
        try {
            $availableAgents = $this->agentService->getAvailableAgents();
            
            return $this->json([
                'status' => 'success',
                'data' => $availableAgents,
                'message' => 'Agents disponibles récupérés avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des agents disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
