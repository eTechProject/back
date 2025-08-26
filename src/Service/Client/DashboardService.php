<?php

namespace App\Service\Client;

use App\DTO\Dashboard\Request\DashboardFiltersDTO;
use App\DTO\Dashboard\Response\DashboardResponseDTO;
use App\Repository\ServiceOrdersRepository;
use App\Repository\PaymentRepository;
use App\Repository\AgentsRepository;
use App\Repository\TasksRepository;
use App\Repository\PaymentHistoryRepository;

class DashboardService
{
    /**
     * Calcule la distance totale parcourue à partir des positions assign_position (WKT POINT)
     */
    private function calculateTotalDistance(array $tasks): float
    {
        $total = 0.0;
        $lastPoint = null;
        foreach ($tasks as $task) {
            if (method_exists($task, 'getAssignPosition')) {
                $point = $task->getAssignPosition(); // Format WKT: "POINT(lon lat)"
                if ($lastPoint) {
                    $total += $this->haversineDistance($lastPoint, $point);
                }
                $lastPoint = $point;
            }
        }
        return round($total, 2); // en kilomètres
    }

    /**
     * Calcule la distance entre deux points WKT (Haversine)
     */
    private function haversineDistance(string $pointA, string $pointB): float
    {
        // Extrait les coordonnées du WKT
        if (!preg_match('/POINT\\(([-\\d\\.]+) ([-\\d\\.]+)\\)/', $pointA, $a)) return 0;
        if (!preg_match('/POINT\\(([-\\d\\.]+) ([-\\d\\.]+)\\)/', $pointB, $b)) return 0;
        $lon1 = (float)$a[1];
        $lat1 = (float)$a[2];
        $lon2 = (float)$b[1];
        $lat2 = (float)$b[2];
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $a = sin($dLat/2) * sin($dLat/2) +
             sin($dLon/2) * sin($dLon/2) * cos($lat1) * cos($lat2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
    public function __construct(
        private readonly ServiceOrdersRepository $serviceOrdersRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly AgentsRepository $agentsRepository,
        private readonly TasksRepository $tasksRepository,
        private readonly PaymentHistoryRepository $paymentHistoryRepository
    ) {}

    private function buildTasksChart(array $tasks, int $clientId = null): array {
        // Utilisation de la requête optimisée si clientId fourni
        if ($clientId !== null) {
            $data = $this->tasksRepository->countTasksByMonthForClient($clientId);
            return [
                'labels' => array_keys($data),
                'data' => array_values($data)
            ];
        }
        // fallback PHP (si pas d'id)
        $result = [];
        foreach ($tasks as $task) {
            if (method_exists($task, 'getStartDate')) {
                $month = $task->getStartDate()->format('Y-m');
                if (!isset($result[$month])) {
                    $result[$month] = 0;
                }
                $result[$month]++;
            }
        }
        return [
            'labels' => array_keys($result),
            'data' => array_values($result)
        ];
    }

    private function buildIncidentsChart(array $tasks, int $clientId = null): array {
        if ($clientId !== null) {
            $data = $this->tasksRepository->countIncidentsByMonthForClient($clientId);
            return [
                'labels' => array_keys($data),
                'data' => array_values($data)
            ];
        }
        // fallback PHP (si pas d'id)
        $result = [];
        foreach ($tasks as $task) {
            if (method_exists($task, 'getDescription') && method_exists($task, 'getStartDate')) {
                $desc = $task->getDescription();
                if ($desc && stripos($desc, 'incident') !== false) {
                    $month = $task->getStartDate()->format('Y-m');
                    if (!isset($result[$month])) {
                        $result[$month] = 0;
                    }
                    $result[$month]++;
                }
            }
        }
        return [
            'labels' => array_keys($result),
            'data' => array_values($result)
        ];
    }


    private function buildFinancialChart(int $clientId): array {
        $data = $this->paymentHistoryRepository->sumPaymentsByMonthForClient($clientId);
        return [
            'labels' => array_keys($data),
            'data' => array_values($data)
        ];
    }

    public function getDashboardData(int $clientId, ?DashboardFiltersDTO $filters = null): DashboardResponseDTO
    {
        $response = new DashboardResponseDTO();

        $response->filters = [
            'choice' => $filters?->choice,
            'dateStart' => $filters?->dateStart,
            'dateEnd' => $filters?->dateEnd,
        ];

        $orders = $this->serviceOrdersRepository->findByClientId($clientId);
        $tasks = [];
        $agents = [];
        foreach ($orders as $order) {
            foreach ($order->getTasks() ?? [] as $task) {
                $tasks[] = $task;
                if (method_exists($task, 'getAgent')) {
                    $agent = $task->getAgent();
                    if ($agent && !in_array($agent, $agents, true)) {
                        $agents[] = $agent;
                    }
                }
            }
        }

        // Filtrage uniquement si un filtre est appliqué
        if ($filters?->choice || $filters?->dateStart || $filters?->dateEnd) {
            $now = new \DateTimeImmutable('now');
            $tasks = array_filter($tasks, function ($task) use ($filters, $now) {
                if (!method_exists($task, 'getStartDate')) return false;
                $start = $task->getStartDate();
                if (!$start) return false;
                // Filtrage par choix rapide
                if ($filters->choice) {
                    switch ($filters->choice) {
                        case 'today':
                            if ($start->format('Y-m-d') !== $now->format('Y-m-d')) return false;
                            break;
                        case 'last7days':
                            $sevenDaysAgo = $now->modify('-6 days')->setTime(0,0,0);
                            if ($start < $sevenDaysAgo || $start > $now) return false;
                            break;
                        case 'thisMonth':
                            if ($start->format('Y-m') !== $now->format('Y-m')) return false;
                            break;
                        case 'last30days':
                            $thirtyDaysAgo = $now->modify('-29 days')->setTime(0,0,0);
                            if ($start < $thirtyDaysAgo || $start > $now) return false;
                            break;
                        case 'thisYear':
                            if ($start->format('Y') !== $now->format('Y')) return false;
                            break;
                        default:
                            break;
                    }
                }
                // Filtrage par plage personnalisée
                if ($filters->dateStart) {
                    $dateStart = new \DateTimeImmutable($filters->dateStart);
                    if ($start < $dateStart) return false;
                }
                if ($filters->dateEnd) {
                    $dateEnd = new \DateTimeImmutable($filters->dateEnd);
                    if ($start > $dateEnd) return false;
                }
                return true;
            });
            $tasks = array_values($tasks);
        }

        // Paiements du client
        $payments = $this->paymentRepository->findByClient($clientId);

        // KPIs
        $response->kpis = [
            'tasks' => count($tasks),
            'activeAgents' => count($agents),
            'duration' => array_sum(array_map(function($t) {
                if (method_exists($t, 'getEndDate') && method_exists($t, 'getStartDate')) {
                    $end = $t->getEndDate();
                    $start = $t->getStartDate();
                    return ($end && $start) ? ($end->getTimestamp() - $start->getTimestamp()) : 0;
                }
                return 0;
            }, $tasks)),
            'distance' => $this->calculateTotalDistance($tasks),
            'incidents' => count(array_filter($tasks, function($task) {
                return method_exists($task, 'getDescription') && $task->getDescription() && stripos($task->getDescription(), 'incident') !== false;
            })),
            'subscription' => !empty($payments),
        ];

        // Graphiques (exemple, à adapter)
        $response->charts = [
            'tasks' => $this->buildTasksChart($tasks, $clientId),
            'incidents' => $this->buildIncidentsChart($tasks, $clientId),
            'performance' => $this->buildPerformanceChart($tasks, $agents, $clientId),
            'financial' => $this->buildFinancialChart($clientId),
        ];

        // Indicateurs complémentaires (exemple, à adapter)
        $response->indicators = [
            'topAgents' => $this->getTopAgents($tasks, $clientId),
            'productiveDays' => $this->getProductiveDays($tasks),
            'punctuality' => $this->getPunctuality($tasks),
        ];

        return $response;
    }

    // Méthodes privées pour construire les différentes sections (à implémenter selon la logique métier)
    private function buildPerformanceChart(array $tasks, array $agents, int $clientId = null): array {
        if ($clientId !== null) {
            $data = $this->tasksRepository->countTasksByAgentForClient($clientId);
            return [
                'labels' => array_keys($data),
                'data' => array_values($data)
            ];
        }
        // fallback PHP (si pas d'id)
        $result = [];
        foreach ($agents as $agent) {
            $agentName = method_exists($agent, 'getName') ? $agent->getName() : ($agent->getId() ?? '');
            $result[$agentName] = 0;
        }
        foreach ($tasks as $task) {
            if (method_exists($task, 'getAgent')) {
                $agent = $task->getAgent();
                $agentName = method_exists($agent, 'getName') ? $agent->getName() : ($agent->getId() ?? '');
                if (isset($result[$agentName])) {
                    $result[$agentName]++;
                }
            }
        }
        return [
            'labels' => array_keys($result),
            'data' => array_values($result)
        ];
    }

    private function buildActivityChart(array $tasks): array {
        // Exemple : nombre de tâches par jour de la semaine
        $result = ['Mon'=>0,'Tue'=>0,'Wed'=>0,'Thu'=>0,'Fri'=>0,'Sat'=>0,'Sun'=>0];
        foreach ($tasks as $task) {
            if (method_exists($task, 'getStartDate')) {
                $day = $task->getStartDate()->format('D');
                if (isset($result[$day])) {
                    $result[$day]++;
                }
            }
        }
        return [
            'labels' => array_keys($result),
            'data' => array_values($result)
        ];
    }

    private function getTopAgents(array $tasks, int $clientId = null): array {
        if ($clientId !== null) {
            return $this->tasksRepository->getTopAgentsForClient($clientId);
        }
        // fallback PHP (si pas d'id)
        $counts = [];
        foreach ($tasks as $task) {
            if (method_exists($task, 'getAgent')) {
                $agent = $task->getAgent();
                $agentName = method_exists($agent, 'getName') ? $agent->getName() : ($agent->getId() ?? '');
                if (!isset($counts[$agentName])) {
                    $counts[$agentName] = 0;
                }
                $counts[$agentName]++;
            }
        }
        arsort($counts);
        return array_slice($counts, 0, 3, true);
    }

    private function getProductiveDays(array $tasks): array {
        // Exemple : jours avec le plus de tâches
        $days = [];
        foreach ($tasks as $task) {
            if (method_exists($task, 'getStartDate')) {
                $date = $task->getStartDate()->format('Y-m-d');
                if (!isset($days[$date])) {
                    $days[$date] = 0;
                }
                $days[$date]++;
            }
        }
        arsort($days);
        return array_slice($days, 0, 5, true);
    }

    private function getPunctuality(array $tasks): array {
        // Exemple : taux de ponctualité (tâches terminées à l'heure)
        $onTime = 0;
        $total = 0;
        foreach ($tasks as $task) {
            if (method_exists($task, 'getEndDate') && method_exists($task, 'getStartDate')) {
                $start = $task->getStartDate();
                $end = $task->getEndDate();
                if ($start && $end) {
                    $total++;
                    if ($end <= $start->modify('+1 day')) { // Ex : ponctuel si fini en moins de 24h
                        $onTime++;
                    }
                }
            }
        }
        return [
            'punctualityRate' => $total > 0 ? round($onTime / $total * 100, 2) : null,
            'totalTasks' => $total
        ];
    }
}
