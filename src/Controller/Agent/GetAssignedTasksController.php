<?php

namespace App\Controller\Agent;

use App\Service\AgentTaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_AGENT')]
#[Route('/api/agent/{encryptedAgentId}/assigned-tasks', name: 'api_agent_assigned_tasks', methods: ['GET'])]
class GetAssignedTasksController extends AbstractController
{
    public function __construct(
        private readonly AgentTaskService $agentTaskService
    ) {}

    public function __invoke(Request $request, string $encryptedAgentId): JsonResponse
    {
        try {
            // Note: encryptedAgentId is actually the encrypted user ID linked to the agent
            $tasksDTOs = $this->agentTaskService->getAssignedTasksByEncryptedAgentId($encryptedAgentId);

            return $this->json([
                'status' => 'success',
                'data' => $tasksDTOs,
                'total' => count($tasksDTOs)
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des tâches assignées'
            ], 500);
        }
    }
}
