<?php

namespace App\Controller\Admin;

use App\Service\Admin\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/reports/types', name: 'api_admin_reports_types', methods: ['GET'])]
class ReportTypesController extends AbstractController
{
    public function __construct(
        private readonly ReportService $reportService
    ) {
    }

    public function __invoke(): JsonResponse
    {
        try {
            $responseDto = $this->reportService->getReportTypes();
            
            return $this->json([
                'status' => 'success',
                'message' => 'Types de rapports récupérés avec succès',
                'data' => $responseDto->toArray()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des types de rapports'
            ], 500);
        }
    }
}
