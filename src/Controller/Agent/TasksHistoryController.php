<?php

namespace App\Controller\Agent;

use App\Service\AgentService;
use App\Service\RequestValidationService;
use App\Service\TaskHistoryResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_AGENT')]
#[Route('/api/agent/tasks-history', name: 'api_agent_tasks_history', methods: ['GET'])]
class TasksHistoryController extends AbstractController
{
    public function __construct(
        private AgentService $agentService,
        private RequestValidationService $requestValidationService,
        private TaskHistoryResponseService $taskHistoryResponseService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            // Récupérer l'agent connecté
            $user = $this->getUser();
            $agent = $this->agentService->getAgentByUser($user);
            
            if (!$agent) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Agent non trouvé'
                ], 404);
            }

            // Validation des paramètres via le service
            [$page, $limit] = $this->requestValidationService->validatePaginationParams($request);
            $statusFilter = $this->requestValidationService->validateStatusParam($request);
            
            // Construction de la réponse via le service
            $response = $this->taskHistoryResponseService->buildTaskHistoryResponse(
                $agent, 
                $page, 
                $limit, 
                $statusFilter
            );

            return $this->json($response);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de l\'historique des tâches'
            ], 500);
        }
    }
}
