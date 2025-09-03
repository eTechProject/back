<?php

namespace App\Controller\Admin;

use App\Service\Admin\ReportService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/reports/download/{encryptedReportId}', name: 'api_admin_reports_download', methods: ['GET'])]
class ReportDownloadController extends AbstractController
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly CryptService $cryptService
    ) {
    }

    public function __invoke(string $encryptedReportId, Request $request): Response
    {
        try {
            // Décrypter l'ID du rapport
            $reportId = $this->cryptService->decryptId($encryptedReportId, EntityType::REPORT->value);
            if (!$reportId) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'ID de rapport invalide'
                ], 400);
            }

            $format = $request->query->get('format', 'pdf');
            
            $file = $this->reportService->downloadReport($reportId, $format);
            
            return $this->file($file);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors du téléchargement du rapport',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
