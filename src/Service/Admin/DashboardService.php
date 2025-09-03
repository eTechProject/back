<?php

declare(strict_types=1);

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
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;

class DashboardService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ServiceOrdersRepository $serviceOrdersRepository
    ) {
    }

    public function getDashboardStats(DashboardStatsRequestDTO $requestDto): DashboardStatsResponseDTO
    {
        $startDate = $requestDto->getStartDate() ?? new \DateTime('-' . $requestDto->getPeriodInDays() . ' days');
        $endDate = $requestDto->getEndDate() ?? new \DateTime();

        // Calcul des statistiques principales
        $totalUsers = $this->userRepository->count([]);
        $totalAgents = $this->userRepository->count(['role' => UserRole::AGENT]);
    $totalClients = $this->userRepository->count(['role' => UserRole::CLIENT]);
        $totalOrders = $this->serviceOrdersRepository->count([]);

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

        $stats = [
            (function() use ($totalUsers, $currentPeriodUsers, $previousPeriodUsers) {
                $variation = $this->calculateVariation($currentPeriodUsers, $previousPeriodUsers);
                $trend = $variation > 0 ? 'up' : ($variation < 0 ? 'down' : 'stable');
                $color = $variation > 0 ? 'success' : ($variation < 0 ? 'danger' : 'secondary');
                return new DashboardStatDTO(
                    'total_users',
                    'Utilisateurs Total',
                    $totalUsers,
                    $variation,
                    'users',
                    $color,
                    $trend
                );
            })(),
            (function() use ($totalAgents) {
                $variation = 0;
                $trend = 'stable';
                $color = 'secondary';
                return new DashboardStatDTO(
                    'total_agents',
                    'Agents Total',
                    $totalAgents,
                    $variation,
                    'user-tie',
                    $color,
                    $trend
                );
            })(),
            (function() use ($totalClients) {
                $variation = 0;
                $trend = 'stable';
                $color = 'secondary';
                return new DashboardStatDTO(
                    'total_clients',
                    'Clients Total',
                    $totalClients,
                    $variation,
                    'user-friends',
                    $color,
                    $trend
                );
            })(),
            (function() use ($totalOrders, $currentPeriodOrders, $previousPeriodOrders) {
                $variation = $this->calculateVariation($currentPeriodOrders, $previousPeriodOrders);
                $trend = $variation > 0 ? 'up' : ($variation < 0 ? 'down' : 'stable');
                $color = $variation > 0 ? 'success' : ($variation < 0 ? 'danger' : 'secondary');
                return new DashboardStatDTO(
                    'total_orders',
                    'Commandes Total',
                    $totalOrders,
                    $variation,
                    'shopping-cart',
                    $color,
                    $trend
                );
            })()
        ];

        return new DashboardStatsResponseDTO($stats, $startDate, $endDate, $requestDto->period);
    }

    public function getRecentActivities(RecentActivitiesRequestDTO $requestDto): RecentActivitiesResponseDTO
    {
        // Pour l'instant, simulation d'activités basées sur les commandes récentes
        $recentOrders = $this->serviceOrdersRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            $requestDto->limit
        );

        $activities = [];
        foreach ($recentOrders as $order) {
            $activities[] = new ActivityDTO(
                'order_created',
                'Nouvelle commande créée',
                $order->getCreatedAt(),
                $order->getClient()?->getEmail() ?? 'Utilisateur inconnu',
                [
                    'order_id' => $order->getId(),
                    'user_email' => $order->getClient()?->getEmail()
                ]
            );
        }

        return new RecentActivitiesResponseDTO($activities, $requestDto->limit);
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
                'create_user',
                'Créer un utilisateur',
                '/api/admin/clients', // modifié ici
                'POST',
                'user-plus',
                true
            ),
            new QuickActionDTO(
                'view_orders',
                'Voir les commandes',
                '/api/admin/orders',
                'GET',
                'list',
                true
            ),
            new QuickActionDTO(
                'generate_report',
                'Générer un rapport',
                '/api/admin/reports/generate',
                'POST',
                'file-text',
                true
            ),
            new QuickActionDTO(
                'view_agents',
                'Gérer les agents',
                '/api/admin/agents',
                'GET',
                'users',
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
}
