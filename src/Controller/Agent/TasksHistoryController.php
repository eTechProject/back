<?php

namespace App\Controller\Agent;

use App\Service\AgentService;
use App\Service\RequestValidationService;
use App\Service\TaskHistoryResponseService;
use App\DTO\Dashboard\Request\DashboardFiltersDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_AGENT')]
#[Route('/api/agent/tasks-history', name: 'api_agent_tasks_history', methods: ['GET'])]
class TasksHistoryController extends AbstractController
{
    public function __construct(
        private AgentService $agentService,
        private RequestValidationService $requestValidationService,
        private TaskHistoryResponseService $taskHistoryResponseService,
        private readonly ValidatorInterface $validator
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
            
            // Créer et valider les filtres de dashboard
            $filters = new DashboardFiltersDTO();
            $filters->dateRange = $request->query->get('dateRange', 'all');
            $filters->choice = $request->query->get('choice');
            $filters->dateStart = $request->query->get('dateStart');
            $filters->dateEnd = $request->query->get('dateEnd');

             $errors = $this->validator->validate($filters);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                
                return $this->json([
                    'status' => 'error',
                    'message' => 'Données invalides',
                    'errors' => $errorMessages
                ], 400);
            }
            

            $response = $this->taskHistoryResponseService->buildTaskHistoryAgentResponse(
                $agent, 
                $page, 
                $limit, 
                $statusFilter,
                null,
                $filters
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
