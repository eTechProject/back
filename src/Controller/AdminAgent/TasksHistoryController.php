<?php

namespace App\Controller\AdminAgent;

use App\Service\AgentService;
use App\Service\CryptService;
use App\Service\RequestValidationService;
use App\Service\TaskHistoryResponseService;
use App\Enum\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/agent-tasks/{encryptedId}', name: 'api_admin_agent_tasks_history', methods: ['GET'])]
class TasksHistoryController extends AbstractController
{
    public function __construct(
        private AgentService $agentService,
        private CryptService $cryptService,
        private RequestValidationService $requestValidationService,
        private TaskHistoryResponseService $taskHistoryResponseService
    ) {}

    public function __invoke(string $encryptedId, Request $request): JsonResponse
    {
        try {
            // Décrypter et récupérer l'agent
            $agentId = $this->cryptService->decryptId($encryptedId, EntityType::AGENT->value);
            
            if (!$agentId) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'ID agent invalide'
                ], 400);
            }

            $agent = $this->agentService->getAgent($agentId);
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
                $statusFilter,
                $encryptedId
            );
            
            // Mettre à jour le message pour l'admin
            $response['message'] = 'Historique des tâches de l\'agent récupéré avec succès';

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
