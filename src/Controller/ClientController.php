<?php

namespace App\Controller;

use App\Service\AgentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/client', name: 'api_client_')]
class ClientController extends AbstractController
{
    public function __construct(
        private AgentService $agentService,
        private SerializerInterface $serializer
    ) {}

    #[Route('/available-agents', name: 'available_agents', methods: ['GET'])]
    public function getAvailableAgents(): JsonResponse
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
