<?php

namespace App\Controller\AdminAgent;

use App\Service\AgentService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/agents', name: 'api_admin_agents_list', methods: ['GET'])]
class ListController extends AbstractController
{
    public function __construct(private AgentService $agentService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));
        [$agents, $total] = $this->agentService->getAgentsPaginated($page, $limit);
        $agentDtos = [];
        foreach ($agents as $agent) {
            $agentDtos[] = $this->agentService->getAgentProfile($agent);
        }
        $pages = (int) ceil($total / $limit);

        return $this->json([
            'status' => 'success',
            'data' => $agentDtos,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }
}