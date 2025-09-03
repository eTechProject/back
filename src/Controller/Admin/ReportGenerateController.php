<?php

namespace App\Controller\Admin;

use App\DTO\Report\Request\GenerateReportRequestDTO;
use App\Service\Admin\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Report;
use App\Service\CryptService;
use App\Enum\EntityType;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/reports/generate', name: 'api_admin_reports_generate', methods: ['POST'])]
class ReportGenerateController extends AbstractController
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly CryptService $cryptService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $requestDto = new GenerateReportRequestDTO(
                type: $data['type'] ?? 'users',
                format: $data['format'] ?? 'json',
                startDate: $data['start_date'] ?? null,
                endDate: $data['end_date'] ?? null,
                filters: $data['filters'] ?? []
            );

            $responseDto = $this->reportService->generateReport($requestDto);
            $report = $responseDto['report_entity'] ?? null;
            unset($responseDto['report_entity']);
            if (!$report || !$report->getId()) {
                throw new \RuntimeException('Impossible de récupérer l\'ID du rapport généré');
            }
            $encryptedReportId = $this->cryptService->encryptId($report->getId(), EntityType::REPORT->value);

            return $this->json([
                'status' => 'success',
                'message' => 'Rapport généré avec succès',
                'data' => array_merge(
                    is_array($responseDto) ? $responseDto : $responseDto->toArray(),
                    [
                        'report_id' => $encryptedReportId
                    ]
                )
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la génération du rapport',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
