<?php

namespace App\Controller\Admin;

use App\DTO\Dashboard\Request\DashboardStatsRequestDTO;
use App\Service\Admin\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/dashboard/stats', name: 'api_admin_dashboard_stats', methods: ['GET'])]
class DashboardStatsController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $period = $request->query->get('period', 'month');
            $requestDto = new DashboardStatsRequestDTO(
                startDate: $request->query->get('start_date'),
                endDate: $request->query->get('end_date'),
                period: $period
            );
            $responseDto = $this->dashboardService->getDashboardStats($requestDto);
            return $this->json($responseDto->toArray());
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
}
