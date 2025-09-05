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
#[Route('/api/admin/dashboard/overview', name: 'api_admin_dashboard_overview', methods: ['GET'])]
class DashboardOverviewController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $requestDto = new DashboardStatsRequestDTO(
                period: $request->query->get('period', 'month'),
                startDate: $request->query->get('start_date'),
                endDate: $request->query->get('end_date')
            );

            $responseDto = $this->dashboardService->getDashboardOverview($requestDto);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Vue d\'ensemble récupérée avec succès',
                'data' => $responseDto->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de la vue d\'ensemble'
            ], 500);
        }
    }
}
