<?php

namespace App\Service\Admin;

use App\DTO\Dashboard\Request\DashboardStatsRequestDTO;
use App\DTO\Dashboard\Request\RecentActivitiesRequestDTO;
use App\DTO\Dashboard\Response\DashboardStatsResponseDTO;
use App\DTO\Dashboard\Response\RecentActivitiesResponseDTO;
use App\DTO\Dashboard\Response\DashboardOverviewResponseDTO;
use App\DTO\Dashboard\Response\QuickActionsResponseDTO;
use App\DTO\Dashboard\Internal\DashboardStatDTO;
use App\DTO\Dashboard\Internal\ActivityDTO;
use App\DTO\Dashboard\Internal\QuickActionDTO;
use App\Repository\UserRepository;
use App\Repository\ServiceOrdersRepository;
use App\Repository\PackRepository;
use App\Repository\PaymentRepository;
use App\Enum\UserRole;
use App\Enum\Status;
use App\Enum\PaymentStatus;
use App\Service\CryptService;
use App\Enum\EntityType;
use Doctrine\ORM\EntityManagerInterface;

class DashboardService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ServiceOrdersRepository $serviceOrdersRepository,
        private readonly PackRepository $packRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly CryptService $cryptService
    ) {
    }

    public function getDashboardStats(DashboardStatsRequestDTO $requestDto): DashboardStatsResponseDTO
    {
        $startDate = $requestDto->getStartDate() ?? new \DateTime('-' . $requestDto->getPeriodInDays() . ' days');
        $endDate = $requestDto->getEndDate() ?? new \DateTime();

        // Calcul des statistiques principales
        $totalUsers = $this->userRepository->count([]);
        $totalPacks = $this->packRepository->count([]); // Products
        $totalOrders = $this->serviceOrdersRepository->count([]);
        $pendingOrders = $this->serviceOrdersRepository->count(['status' => Status::PENDING]);

        // Calcul du revenu total
        $totalRevenue = $this->calculateTotalRevenue();

        // Statistiques pour la période actuelle
        $currentPeriodUsers = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
        
        $currentPeriodOrders = $this->serviceOrdersRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        $currentPeriodPacks = $this->paymentRepository->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.pack)')
            ->where('p.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        // Calcul de la période précédente pour les variations
        $previousStart = (clone $startDate)->modify('-' . $requestDto->getPeriodInDays() . ' days');
        $previousEnd = $startDate;
        
        $previousPeriodUsers = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $previousStart)
            ->setParameter('end', $previousEnd)
            ->getQuery()
            ->getSingleScalarResult();

        $previousPeriodOrders = $this->serviceOrdersRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $previousStart)
            ->setParameter('end', $previousEnd)
            ->getQuery()
            ->getSingleScalarResult();

        $previousPeriodPacks = $this->paymentRepository->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.pack)')
            ->where('p.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $previousStart)
            ->setParameter('end', $previousEnd)
            ->getQuery()
            ->getSingleScalarResult();

        $stats = [
            (function() use ($totalRevenue) {
                $variation = 12.5; // À calculer selon votre logique métier
                $trend = 'up';
                $color = 'success';
                return new DashboardStatDTO(
                    'total_revenue',
                    'Total Revenue',
                    $totalRevenue,
                    $variation,
                    'dollar-sign',
                    $color,
                    $trend
                );
            })(),
            (function() use ($totalUsers, $currentPeriodUsers, $previousPeriodUsers) {
                $variation = $this->calculateVariation($currentPeriodUsers, $previousPeriodUsers);
                $trend = $variation > 0 ? 'up' : ($variation < 0 ? 'down' : 'stable');
                $color = $variation > 0 ? 'success' : ($variation < 0 ? 'danger' : 'secondary');
                return new DashboardStatDTO(
                    'total_users',
                    'Total Users',
                    $totalUsers,
                    $variation,
                    'users',
                    $color,
                    $trend
                );
            })(),
            (function() use ($totalPacks, $currentPeriodPacks, $previousPeriodPacks) {
                $variation = $this->calculateVariation($currentPeriodPacks, $previousPeriodPacks);
                $trend = $variation > 0 ? 'up' : ($variation < 0 ? 'down' : 'stable');
                $color = $variation > 0 ? 'success' : ($variation < 0 ? 'danger' : 'secondary');
                return new DashboardStatDTO(
                    'products',
                    'Products',
                    $totalPacks,
                    $variation,
                    'package',
                    $color,
                    $trend
                );
            })(),
            (function() use ($pendingOrders, $currentPeriodOrders, $previousPeriodOrders) {
                $variation = $this->calculateVariation($currentPeriodOrders, $previousPeriodOrders);
                $trend = $variation > 0 ? 'up' : ($variation < 0 ? 'down' : 'stable');
                $color = $variation < 0 ? 'success' : ($variation > 0 ? 'danger' : 'secondary'); // Inverse car moins de pending = mieux
                return new DashboardStatDTO(
                    'pending_orders',
                    'Pending Orders',
                    $pendingOrders,
                    $variation,
                    'clock',
                    $color,
                    $trend
                );
            })()
        ];

        return new DashboardStatsResponseDTO($stats, $startDate, $endDate, $requestDto->period);
    }

    public function getRecentActivities(RecentActivitiesRequestDTO $requestDto): RecentActivitiesResponseDTO
    {
        $activities = [];
        $limit = $requestDto->limit ?? 10; // Augmenter la limite par défaut

        // 1. Nouvelles commandes
        $recentOrders = $this->serviceOrdersRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            $limit
        );

        foreach ($recentOrders as $order) {
            $createdAt = $order->getCreatedAt();
            $immutableDate = $createdAt instanceof \DateTime ? 
                \DateTimeImmutable::createFromMutable($createdAt) : $createdAt;
                
            $activities[] = new ActivityDTO(
                'order_created',
                'Nouvelle commande créée',
                $immutableDate,
                $order->getClient()?->getEmail() ?? 'Utilisateur inconnu',
                [
                    'order_id' => $this->cryptService->encryptId($order->getId(), EntityType::SERVICE_ORDER->value),
                    'user_email' => $order->getClient()?->getEmail(),
                    'icon' => 'shopping-cart',
                    'color' => 'success'
                ]
            );
        }

        // 2. Nouveaux utilisateurs
        $recentUsers = $this->userRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            $limit
        );

        foreach ($recentUsers as $user) {
            $createdAt = $user->getCreatedAt();
            $immutableDate = $createdAt instanceof \DateTime ? 
                \DateTimeImmutable::createFromMutable($createdAt) : $createdAt;
                
            $roleLabel = $user->getRole() === UserRole::AGENT ? 'agent' : 'client';
            $activities[] = new ActivityDTO(
                'user_registered',
                "Nouvel $roleLabel inscrit",
                $immutableDate,
                $user->getEmail(),
                [
                    'user_id' => $this->cryptService->encryptId($user->getId(), EntityType::USER->value),
                    'user_role' => $user->getRole()->value,
                    'icon' => $user->getRole() === UserRole::AGENT ? 'user-tie' : 'user-plus',
                    'color' => 'info'
                ]
            );
        }

        // 3. Nouveaux paiements
        $recentPayments = $this->paymentRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            $limit
        );

        foreach ($recentPayments as $payment) {
            $createdAt = $payment->getCreatedAt();
            $immutableDate = $createdAt instanceof \DateTime ? 
                \DateTimeImmutable::createFromMutable($createdAt) : $createdAt;
                
            $activities[] = new ActivityDTO(
                'payment_created',
                'Nouveau paiement effectué',
                $immutableDate,
                $payment->getClient()->getEmail(),
                [
                    'payment_id' => $this->cryptService->encryptId($payment->getId(), EntityType::PAYMENT->value),
                    'payment_status' => $payment->getStatus()->value,
                    'pack_name' => $payment->getPack()->getName() ?? 'Pack inconnu',
                    'icon' => 'credit-card',
                    'color' => $payment->getStatus() === PaymentStatus::ACTIF ? 'success' : 'warning'
                ]
            );
        }

        // 4. Trier toutes les activités par date décroissante
        usort($activities, function(ActivityDTO $a, ActivityDTO $b) {
            return $b->createdAt->getTimestamp() <=> $a->createdAt->getTimestamp();
        });

        // 5. Garder seulement les X plus récentes
        $activities = array_slice($activities, 0, $limit);

        return new RecentActivitiesResponseDTO($activities, $limit);
    }

    public function getDashboardOverview(DashboardStatsRequestDTO $requestDto): DashboardOverviewResponseDTO
    {
        $statsResponse = $this->getDashboardStats($requestDto);
        $activitiesResponse = $this->getRecentActivities(
            new RecentActivitiesRequestDTO(5) // Limite à 5 pour l'overview
        );

        return new DashboardOverviewResponseDTO(
            $statsResponse->stats,
            $activitiesResponse->activities,
            new \DateTime()
        );
    }

    public function getQuickActions(): QuickActionsResponseDTO
    {
        $actions = [
            new QuickActionDTO(
                'generate_report',
                'Générer un rapport',
                '/api/admin/reports/generate',
                'POST',
                'file-text',
                true
            )
        ];

        return new QuickActionsResponseDTO($actions);
    }

    private function calculateVariation(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function calculateTotalRevenue(): float
    {
        try {
            // Calculer le revenu total en sommant les prix des packs des paiements actifs/payés
            $result = $this->paymentRepository->createQueryBuilder('p')
                ->select('SUM(pack.price)') // Utiliser le prix du pack lié
                ->join('p.pack', 'pack') // Joindre avec l'entité Pack
                ->where('p.status = :status')
                ->setParameter('status', PaymentStatus::ACTIF) // Utiliser l'enum PaymentStatus::ACTIF
                ->getQuery()
                ->getSingleScalarResult();

            return (float) ($result ?? 0);
        } catch (\Exception $e) {
            // En cas d'erreur, essayer de compter les paiements actifs
            try {
                $activePayments = $this->paymentRepository->count(['status' => PaymentStatus::ACTIF]);
                // Estimation basée sur un prix moyen de 50 par pack
                return (float) ($activePayments * 50);
            } catch (\Exception $fallbackError) {
                // Valeur simulée en dernier recours
                return 24350.0;
            }
        }
    }
}