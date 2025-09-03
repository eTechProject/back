<?php

namespace App\Controller\Admin;

use App\Service\Admin\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/dashboard/quick-actions', name: 'api_admin_dashboard_quick_actions', methods: ['GET'])]
class DashboardQuickActionsController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {
    }

    public function __invoke(): JsonResponse
    {
        try {
            $responseDto = $this->dashboardService->getQuickActions();
            
            return $this->json([
                'status' => 'success',
                'message' => 'Actions rapides récupérées avec succès',
                'data' => $responseDto->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des actions rapides'
            ], 500);
        }
    }
}
