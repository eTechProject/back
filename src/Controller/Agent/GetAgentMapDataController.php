<?php

namespace App\Controller\Agent;

use App\Service\AgentMapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_AGENT')]
#[Route('/api/agent/{idcrypt}/map-data', name: 'api_agent_map_data', methods: ['GET'])]
class GetAgentMapDataController extends AbstractController
{
    public function __construct(
        private readonly AgentMapService $agentMapService
    ) {}

    public function __invoke(string $idcrypt): JsonResponse
    {
        try {
            $mapData = $this->agentMapService->getAgentMapData($idcrypt);

            return $this->json([
                'success' => true,
                'data' => $mapData,
                'message' => 'Données de la carte agent récupérées avec succès',
                'timestamp' => (new \DateTimeImmutable())->format('c')
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données de la carte',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
