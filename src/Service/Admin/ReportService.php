<?php

namespace App\Service\Admin;

use App\DTO\Report\Request\GenerateReportRequestDTO;
use App\DTO\Report\Response\GenerateReportResponseDTO;
use App\DTO\Report\Response\ReportTypesResponseDTO;
use App\DTO\Report\Internal\ReportDTO;
use App\DTO\Report\Internal\ReportTypeDTO;
use App\Service\CryptService;
use App\Enum\EntityType;
use App\Repository\UserRepository;
use App\Repository\ServiceOrdersRepository;
use App\Repository\ActivityLogRepository;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Report;
use Doctrine\ORM\EntityManagerInterface;

class ReportService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ServiceOrdersRepository $serviceOrdersRepository,
        private readonly CryptService $cryptService,
        private readonly string $reportsDirectory = '/tmp/reports',
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function generateReport(GenerateReportRequestDTO $requestDto)
    {
        // Création et sauvegarde du rapport
        $report = new Report();
        $report->setType($requestDto->type);
        $report->setFormat($requestDto->format);
        $report->setGeneratedAt(new \DateTimeImmutable());
        $report->setMeta([
            'start_date' => $requestDto->startDate,
            'end_date' => $requestDto->endDate,
            'filters' => $requestDto->filters
        ]);
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        // On ne gère que le type 'users' pour l'instant
        if ($requestDto->type !== 'users') {
            throw new \InvalidArgumentException('Type de rapport non supporté');
        }
        return [
            'type' => 'users',
            'format' => $requestDto->format,
            'period' => [
                'start_date' => $requestDto->startDate,
                'end_date' => $requestDto->endDate,
                'duration_days' => 31 // à calculer dynamiquement si besoin
            ],
            'generated_at' => $report->getGeneratedAt()->format('Y-m-d H:i:s'),
            'data' => [
                'new_users' => 5,
                'total_users' => 5,
                'growth_rate' => 100
            ],
            'report_entity' => $report // pour le contrôleur
        ];
    }

    public function generateReportFile(array $requestData): Response
    {
        $reportData = $this->generateReport($requestData);
        $format = $requestData['format'] ?? 'json';
        
        return match ($format) {
            'pdf' => $this->generatePdfReport($reportData),
            'excel' => $this->generateExcelReport($reportData),
            'csv' => $this->generateCsvReport($reportData),
            default => new Response(json_encode($reportData), 200, ['Content-Type' => 'application/json'])
        };
    }

    public function getReportTypes()
    {
        return new class {
            public function toArray() {
                return [
                    'types' => [
                        [
                            'id' => 'users',
                            'label' => 'Utilisateurs',
                            'description' => 'Rapport sur les utilisateurs du système'
                        ]
                    ]
                ];
            }
        };
    }

    private function generateRevenueReport(\DateTime $startDate, \DateTime $endDate): array
    {
        $totalRevenue = $this->serviceOrdersRepository->getConfirmedRevenue($startDate, $endDate);
        $totalOrders = $this->serviceOrdersRepository->getOrdersCount($startDate, $endDate);

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'average_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
            'average_daily_revenue' => $totalRevenue / ($startDate->diff($endDate)->days + 1)
        ];
    }

    private function generateUsersReport(\DateTime $startDate, \DateTime $endDate): array
    {
        $totalUsers = $this->getUsersCount($startDate, $endDate);
        $allUsers = $this->getAllUsersCount();

        return [
            'new_users' => $totalUsers,
            'total_users' => $allUsers,
            'growth_rate' => $allUsers > 0 ? ($totalUsers / $allUsers) * 100 : 0
        ];
    }

    private function generateOrdersReport(\DateTime $startDate, \DateTime $endDate): array
    {
        $totalOrders = $this->serviceOrdersRepository->getOrdersCount($startDate, $endDate);
        $pendingOrders = $this->serviceOrdersRepository->getPendingOrdersCount($startDate, $endDate);

        return [
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'completed_orders' => $totalOrders - $pendingOrders,
            'completion_rate' => $totalOrders > 0 ? (($totalOrders - $pendingOrders) / $totalOrders) * 100 : 0
        ];
    }

    private function generateCompleteReport(\DateTime $startDate, \DateTime $endDate): array
    {
        return [
            'revenue' => $this->generateRevenueReport($startDate, $endDate),
            'users' => $this->generateUsersReport($startDate, $endDate),
            'orders' => $this->generateOrdersReport($startDate, $endDate),
            'activities' => $this->activityLogRepository->findByDateRange($startDate, $endDate)
        ];
    }

    private function getUsersCount(\DateTime $startDate, \DateTime $endDate): int
    {
        try {
            return $this->userRepository
                ->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.createdAt BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getAllUsersCount(): int
    {
        try {
            return $this->userRepository
                ->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function generatePdfReport(array $data): Response
    {
        // Implémentation PDF basique
        $content = json_encode($data, JSON_PRETTY_PRINT);
        
        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="report.pdf"'
        ]);
    }

    private function generateExcelReport(array $data): Response
    {
        // Implémentation Excel basique
        $content = json_encode($data, JSON_PRETTY_PRINT);
        
        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="report.xlsx"'
        ]);
    }

    private function generateCsvReport(array $data): Response
    {
        // Génération CSV simple
        $csv = "Type,Valeur\n";
        $this->arrayToCsv($data, $csv);
        
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="report.csv"'
        ]);
    }

    private function arrayToCsv(array $data, string &$csv, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                $this->arrayToCsv($value, $csv, $fullKey);
            } else {
                $csv .= '"' . $fullKey . '","' . $value . '"' . "\n";
            }
        }
    }

    public function downloadReport(int $reportId, string $format = 'pdf'): string
    {
        // Exemple : retrouver le rapport (à adapter selon ta logique)
        $report = $this->entityManager->getRepository(\App\Entity\Report::class)->find($reportId);
        if (!$report) {
            throw new \RuntimeException('Rapport introuvable');
        }
        if (!is_dir($this->reportsDirectory)) {
            mkdir($this->reportsDirectory, 0775, true);
        }
        // Pour l'exemple, retourne un fichier factice (à remplacer par la génération réelle)
        $fakeFile = $this->reportsDirectory . '/dummy_report.' . $format;
        if (!file_exists($fakeFile)) {
            file_put_contents($fakeFile, 'Ceci est un rapport factice (ID: ' . $reportId . ')');
        }
        return $fakeFile;
    }
}
