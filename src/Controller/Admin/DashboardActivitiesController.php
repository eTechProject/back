<?php

namespace App\Controller\Admin;

use App\DTO\Dashboard\Request\RecentActivitiesRequestDTO;
use App\Service\Admin\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/dashboard/activities', name: 'api_admin_dashboard_activities', methods: ['GET'])]
class DashboardActivitiesController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $requestDto = new RecentActivitiesRequestDTO(
                limit: (int) $request->query->get('limit', 10),
                type: $request->query->get('type'),
                userId: $request->query->get('user_id') ? (int) $request->query->get('user_id') : null
            );

            $responseDto = $this->dashboardService->getRecentActivities($requestDto);
            
            return $this->json([
                'status' => 'success',
                'message' => 'Activités récupérées avec succès',
                'data' => $responseDto->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des activités'
            ], 500);
        }
    }
}
