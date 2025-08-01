<?php

namespace App\Controller\AdminAgent;

use App\Service\AgentService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/agents/search', name: 'api_admin_agents_search', methods: ['GET'])]
class SearchController extends AbstractController
{
    public function __construct(private AgentService $agentService) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $name = $request->query->get('name');
            $agents = $this->agentService->searchAgents($name);
            $agentDtos = [];
            foreach ($agents as $agent) {
                $agentDtos[] = $this->agentService->getAgentProfile($agent);
            }

            return $this->json([
                'status' => 'success',
                'data' => $agentDtos,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de l\'agent',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}