<?php

namespace App\Controller\Client;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Client\DashboardService;
use App\Service\RequestValidationService;
use App\Service\TaskHistoryResponseService;
use App\DTO\Dashboard\Request\DashboardFiltersDTO;
use App\Service\CryptService;
use App\Enum\EntityType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;



class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly CryptService $cryptService,
        private RequestValidationService $requestValidationService,
        private TaskHistoryResponseService $taskHistoryResponseService,
        private readonly ValidatorInterface $validator
    ) {}
    #[Route('/api/client/{encryptedId}/dashboard', name: 'client_dashboard', methods: ['GET'])]
    public function __invoke(string $encryptedId, Request $request): JsonResponse
    {
        try {

            [$page, $limit] = $this->requestValidationService->validatePaginationParams($request);
            $statusFilter = $this->requestValidationService->validateStatusParam($request);

            $clientId = $this->cryptService->decryptId($encryptedId, EntityType::USER->value);

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

            $response = $this->dashboardService->getDashboardData($clientId, $filters, $page, $limit, $statusFilter);

            return $this->json($response);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération du dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
