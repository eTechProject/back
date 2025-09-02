<?php

namespace App\Service\Client;

use App\DTO\Dashboard\Request\DashboardFiltersDTO;
use App\DTO\Dashboard\Response\DashboardResponseDTO;
use App\Entity\Payment;
use App\Entity\ServiceOrders;
use App\Enum\PaymentHistoryStatus;
use App\Enum\Status;
use App\Repository\ServiceOrdersRepository;
use App\Repository\PaymentRepository;
use App\Repository\AgentsRepository;
use App\Repository\TasksRepository;
use App\Repository\PaymentHistoryRepository;
use App\Repository\AlertRepository;
use App\Repository\AgentLocationsRawRepository;
use App\Repository\AgentLocationsArchiveRepository;
use App\Repository\MessagesRepository;
use \App\Service\AgentLocationArchiveService;

class DashboardService
{
    public function __construct(
        private readonly ServiceOrdersRepository $serviceOrdersRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly AgentsRepository $agentsRepository,
        private readonly TasksRepository $tasksRepository,
        private readonly PaymentHistoryRepository $paymentHistoryRepository,
        private readonly AlertRepository $alertRepository,
        private readonly AgentLocationsRawRepository $agentLocationsRawRepository,
        private readonly AgentLocationsArchiveRepository $agentLocationsArchiveRepository,
        private readonly MessagesRepository $messagesRepository,
        private readonly AgentLocationArchiveService $agentLocationArchiveService
    ) {}

    public function getDashboardData(int $clientId, ?DashboardFiltersDTO $filters = null): DashboardResponseDTO
    {
        $response = new DashboardResponseDTO();

        $response->filters = [
            'choice' => $filters?->choice,
            'dateStart' => $filters?->dateStart,
            'dateEnd' => $filters?->dateEnd,
        ];

        // Get client's service order (only one per client)
        $order = $this->serviceOrdersRepository->findOneByClientId($clientId);
        if (!$order) {
            return $this->getEmptyDashboard($response);
        }

        $tasks = $this->getFilteredTasks($order, $filters);
        $agents = $this->getUniqueAgents($tasks);
        $payment = $this->paymentRepository->findOneBy(
            ['client' => $clientId],
            ['createdAt' => 'DESC']
        );
        $alerts = $this->alertRepository->findByOrderId($order->getId(), $filters);

        // Calculate KPIs
        $response->kpis = $this->calculateKPIs($tasks, $agents, $payment, $alerts);

        // Build Charts
        $response->charts = [
            'tasksOverTime' => $this->buildTasksOverTimeChart($tasks),
            'taskCompletion' => $this->buildTaskCompletionChart($tasks),
            'agentPunctuality' => $this->buildAgentPunctualityChart($tasks, $clientId),
            'averageResponseTime' => $this->buildAverageResponseTimeChart($order, $agents, $clientId, $filters),
        ];

        return $response;
    }

    private function getFilteredTasks($order, ?DashboardFiltersDTO $filters): array
    {
        $tasks = [];
        foreach ($order->getTasks() ?? [] as $task) {
            $tasks[] = $task;
        }

        if (!$filters || (!$filters->choice && !$filters->dateStart && !$filters->dateEnd)) {
            return $tasks;
        }

        $now = new \DateTimeImmutable('now');
        return array_filter($tasks, function ($task) use ($filters, $now) {
            if (!method_exists($task, 'getStartDate')) return false;
            $start = $task->getStartDate();
            if (!$start) return false;

            // Quick filter options
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
                }
            }

            // Custom date range filter
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
    }

    private function getUniqueAgents(array $tasks): array
    {
        $agents = [];
        foreach ($tasks as $task) {
            if (method_exists($task, 'getAgent')) {
                $agent = $task->getAgent();
                if ($agent && !in_array($agent, $agents, true)) {
                    $agents[] = $agent;
                }
            }
        }
        return $agents;
    }

    private function calculateKPIs(array $tasks, array $agents, ?Payment $payment, array $alerts): array
    {
        $totalTasks = count($tasks);
        $completedTasks = 0;
        $totalDuration = 0;
        $validDurationTasks = 0;
        $totalDistance = 0;

        foreach ($tasks as $task) {
            // Count completed tasks
            if (method_exists($task, 'getStatus') && $task->getStatus()->value === 'completed') {
                $completedTasks++;
            }

            // Calculate average duration
            if (method_exists($task, 'getEndDate') && method_exists($task, 'getStartDate')) {
                $end = $task->getEndDate();
                $start = $task->getStartDate();
                if ($end && $start) {
                    $duration = $end->getTimestamp() - $start->getTimestamp();
                    $totalDuration += $duration;
                    $validDurationTasks++;
                }
            }
        }

        // Calculate average distance per agent
        $agentCount = count($agents);
        if ($agentCount > 0) {
            $totalDistance = $this->calculateTotalDistance($tasks);
            $avgDistancePerAgent = $totalDistance / $agentCount;
        } else {
            $avgDistancePerAgent = 0;
        }

        // Format average duration
        $avgDuration = $validDurationTasks > 0 ? $totalDuration / $validDurationTasks : 0;
        $avgDurationFormatted = $this->formatDuration($avgDuration);

        // Completion rate
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
        $status="inactif";
        if($payment!=null) $status= $payment->getStatus();


        return [
            'totalTasks' => $totalTasks,
            'completionRate' => $completionRate . '%',
            'avgTaskDuration' => $avgDurationFormatted,
            'avgDistancePerAgent' => round($avgDistancePerAgent, 1) . ' km',
            'totalAlerts' => count($alerts),
            'subscription' => $status
        ];
    }

    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%dh %02dm', $hours, $minutes);
    }

    private function buildTasksOverTimeChart(array $tasks): array
    {
        $result = [];
        foreach ($tasks as $task) {
            if (method_exists($task, 'getStartDate')) {
                $date = $task->getStartDate()->format('Y-m-d');
                $result[$date] = ($result[$date] ?? 0) + 1;
            }
        }

        ksort($result);
        return [
            'type' => 'line',
            'labels' => array_keys($result),
            'data' => array_values($result)
            ];
    }

    private function buildTaskCompletionChart(array $tasks): array
    {
        $statusCounts = [
            Status::COMPLETED->value => 0,
            Status::CANCELLED->value => 0,
            Status::PENDING->value => 0,
            Status::IN_PROGRESS->value => 0
        ];

        foreach ($tasks as $task) {
            if (method_exists($task, 'getStatus')) {
                $status = $task->getStatus()->value;
                if (isset($statusCounts[$status])) {
                    $statusCounts[$status]++;
                } else {
                    // Map other statuses to pending
                    $statusCounts[Status::PENDING->value]++;
                }
            }
        }

        $total = array_sum($statusCounts);
        $percentages = [];
        foreach ($statusCounts as $status => $count) {
            $percentages[$status] = $total > 0 ? round(($count / $total) * 100, 1) : 0;
        }

        return [
            'type' => 'doughnut',
            'labels' => [Status::COMPLETED->value, Status::CANCELLED->value, Status::PENDING->value, Status::IN_PROGRESS->value],
            'data' => array_values($percentages)
        ];
    }

    private function buildAgentPunctualityChart(array $tasks, int $clientId): array
    {
        $agentPunctuality = [];

        foreach ($tasks as $task) {
            if (!method_exists($task, 'getAgent') || !method_exists($task, 'getStartDate')) {
                continue;
            }

            $agent = $task->getAgent();
            if (!$agent) continue;

            $agentName = $agent->getUser()->getName();
            
            if (!isset($agentPunctuality[$agentName])) {
                $agentPunctuality[$agentName] = ['total' => 0, 'punctual' => 0];
            }

            $agentPunctuality[$agentName]['total']++;

            // Check punctuality using first agent location
            $isPunctual = $this->isTaskPunctual($task);
            if ($isPunctual) {
                $agentPunctuality[$agentName]['punctual']++;
            }
        }

        $labels = [];
        $data = [];
        foreach ($agentPunctuality as $agentName => $stats) {
            $labels[] = $agentName;
            $punctualityRate = $stats['total'] > 0 ? round(($stats['punctual'] / $stats['total']) * 100, 1) : 0;
            $data[] = $punctualityRate;
        }

        return [
            'type' => 'bar',
            'labels' => $labels,
            'data' => $data
        ];
    }

    private function buildAverageResponseTimeChart(ServiceOrders $order, array $agents, int $clientId, DashboardFiltersDTO $filters): array
    {
        if (!$order) {
            return [
                'type' => 'bar',
                'labels' => [],
                'datasets' => []
            ];
        }

        // Delegate calculation to separate method
        $agentResponseTimes = $this->calculateAgentResponseTimes($order, $agents, $clientId, $filters);

        // Format output for chart
        $labels = [];
        $data = [];
        
        foreach ($agentResponseTimes as $stats) {
            $labels[] = $stats['name'];
            $data[] = round($stats['total'] / ($stats['count'] ?: 1), 1);
        }

        return [
            'type' => 'bar',
            'labels' => $labels,
            'data' => $data
        ];
    }

    private function calculateAgentResponseTimes(ServiceOrders $order, array $agents, int $clientId, DashboardFiltersDTO $filters): array
    {
        $agentResponseTimes = [];

        // Loop through each agent individually
        foreach ($agents as $agent) {

            $agentId = $agent->getId();
            $agentName = $agent->getUser()->getName();

            $agentMessages = $this->messagesRepository->findMessagesForAgentAndOrder($agent->getUser()->getId(), $order->getId(), $filters);

            // Calculate response times for this specific agent
            $responseStats = $this->calculateResponseTimeForAgent($agentMessages, $clientId, $agent->getUser()->getId());

           $agentResponseTimes[$agentId] = [
                'name' => $agentName,
                'total' => $responseStats['total'],
                'count' => $responseStats['count']
            ];
        }

        return $agentResponseTimes;
    }

    private function calculateResponseTimeForAgent(array $messages, int $clientId, int $agentUserID): array
    {
        $stats = ['total' => 0, 'count' => 0];

        if (empty($messages)) {
            return $stats;
        }

        // Sort messages by sent time
        usort($messages, function($a, $b) {
            if (method_exists($a, 'getSentAt') && method_exists($b, 'getSentAt')) {
                return $a->getSentAt() <=> $b->getSentAt();
            }
            return 0;
        });

        // Analyze message patterns to find client→agent response sequences
        for ($i = 0; $i < count($messages) - 1; $i++) {
            $currentMessage = $messages[$i];
            $nextMessage = $messages[$i + 1];

            if (!method_exists($currentMessage, 'getSender') || !method_exists($nextMessage, 'getSender') ||
                !method_exists($currentMessage, 'getSentAt') || !method_exists($nextMessage, 'getSentAt')) {
                continue;
            }

            $currentSender = $currentMessage->getSender();
            $nextSender = $nextMessage->getSender();

            // Check if current message is from client and next is from this specific agent
            $isClientMessage = method_exists($currentSender, 'getId') && $currentSender->getId() === $clientId;
            $isAgentResponse = method_exists($nextSender, 'getId') && $nextSender->getId() === $agentUserID;

            if ($isClientMessage && $isAgentResponse) {
                // Calculate response time between client message and agent reply
                $responseTimeSeconds = $nextMessage->getSentAt()->getTimestamp() - 
                                    $currentMessage->getSentAt()->getTimestamp();
                $responseTimeMinutes = $responseTimeSeconds / 60;

                $stats['total'] += $responseTimeMinutes;
                $stats['count']++;
            }
        }

        return $stats;
    }

    private function isTaskPunctual($task): bool
    {
        if (!method_exists($task, 'getId') || !method_exists($task, 'getStartDate') || !method_exists($task, 'getAgent')) {
            return false;
        }

        $taskId = $task->getId();
        $agent = $task->getAgent();
        $expectedStartTime = $task->getStartDate();

        if (!$agent || !$expectedStartTime) {
            return false;
        }

        $actualStartTime = null;

        // First, check if there's an archived location for this task
        $archivedLocation = $this->agentLocationsArchiveRepository->findByTaskId($taskId);
        
        if ($archivedLocation && method_exists($archivedLocation, 'getStartTime')) {
            $actualStartTime = $archivedLocation->getStartTime();
        } else {
            // Fallback to first raw location if no archive exists
            $firstLocation = $this->agentLocationsRawRepository->findFirstLocationForTask($taskId);
            
            if ($firstLocation && method_exists($firstLocation, 'getRecordedAt')) {
                $actualStartTime = $firstLocation->getRecordedAt();
            }
        }

        if (!$actualStartTime) {
            return false;
        }

        $timeDifference = $actualStartTime->getTimestamp() - $expectedStartTime->getTimestamp();
        
        // Consider punctual if agent started within 15 minutes of expected time
        return abs($timeDifference) <= 900; // 15 minutes in seconds
    }

    /**
     * Calculate average message response time for agents
     * This measures how quickly agents respond to client messages
     */
    private function calculateMessageResponseTime(int $clientId): array
    {
        $order = $this->serviceOrdersRepository->findOneByClientId($clientId);
        if (!$order || !method_exists($order, 'getMessages')) {
            return [];
        }

        $messages = [];
        foreach ($order->getMessages() as $message) {
            $messages[] = $message;
        }

        // Sort messages by sent time
        usort($messages, function($a, $b) {
            if (method_exists($a, 'getSentAt') && method_exists($b, 'getSentAt')) {
                return $a->getSentAt() <=> $b->getSentAt();
            }
            return 0;
        });

        $agentResponseTimes = [];

        // Analyze consecutive messages to find client->agent response patterns
        for ($i = 0; $i < count($messages) - 1; $i++) {
            $clientMessage = $messages[$i];
            $agentMessage = $messages[$i + 1];

            if (!method_exists($clientMessage, 'getSender') || !method_exists($agentMessage, 'getSender') ||
                !method_exists($clientMessage, 'getSentAt') || !method_exists($agentMessage, 'getSentAt')) {
                continue;
            }

            $clientSender = $clientMessage->getSender();
            $agentSender = $agentMessage->getSender();

            // Verify: client message followed by agent response
            $isClientMessage = method_exists($clientSender, 'getId') && $clientSender->getId() === $clientId;
            $isAgentResponse = method_exists($agentSender, 'getId') && $agentSender->getId() !== $clientId;

            if ($isClientMessage && $isAgentResponse) {
                $agentName = $agentSender->getUser()->getName();

                if (!isset($agentResponseTimes[$agentName])) {
                    $agentResponseTimes[$agentName] = ['total' => 0, 'count' => 0];
                }

                $responseTimeSeconds = $agentMessage->getSentAt()->getTimestamp() - 
                                     $clientMessage->getSentAt()->getTimestamp();
                $responseTimeMinutes = max(0, $responseTimeSeconds / 60);

                $agentResponseTimes[$agentName]['total'] += $responseTimeMinutes;
                $agentResponseTimes[$agentName]['count']++;
            }
        }

        return $agentResponseTimes;
    }

    /**
     * Calculate total distance traveled using AgentLocationsArchive pathLength or fallback to raw locations
     */
    private function calculateTotalDistance(array $tasks): float
    {
        $total = 0.0;
        
        foreach ($tasks as $task) {
            if (!method_exists($task, 'getId')) {
                continue;
            }

            $taskId = $task->getId();
            
            // First priority: Get archived location data for this task
            $archivedLocation = $this->agentLocationsArchiveRepository->findByTaskId($taskId);
            
            if ($archivedLocation && method_exists($archivedLocation, 'getPathLength')) {
                $pathLengthMeters = $archivedLocation->getPathLength();
                if ($pathLengthMeters !== null) {
                    // Convert meters to kilometers
                    $pathLengthKm = $pathLengthMeters / 1000;
                    $total += $pathLengthKm;
                    continue; // Skip to next task since we have archived data
                }
            }

            // Fallback: Use raw locations with AgentLocationArchiveService calculation
            $firstLocation = $this->agentLocationsRawRepository->findFirstLocationForTask($taskId);
            $lastLocation = $this->agentLocationsRawRepository->findLastLocationForTask($taskId);
            
            if ($firstLocation && $lastLocation && 
                method_exists($firstLocation, 'getGeom') && method_exists($lastLocation, 'getGeom')) {
                
                $firstCoords = $this->extractCoordinatesFromWKT($firstLocation->getGeom());
                $lastCoords = $this->extractCoordinatesFromWKT($lastLocation->getGeom());
                
                if ($firstCoords && $lastCoords && $firstCoords !== $lastCoords) {
                    // Use AgentLocationArchiveService calculateDistance method
                    $distanceMeters = $this->agentLocationArchiveService->calculateDistance($firstCoords, $lastCoords);
                    $distanceKm = $distanceMeters / 1000;
                    $total += $distanceKm;
                }
            }
        }
        
        return round($total, 2); // in kilometers
    }
    /**
     * Extract coordinates from WKT POINT format
     * Returns [longitude, latitude] array
     */
    private function extractCoordinatesFromWKT(string $wktPoint): ?array
    {
        if (preg_match('/POINT\\(([-\\d\\.]+) ([-\\d\\.]+)\\)/', $wktPoint, $matches)) {
            return [(float)$matches[1], (float)$matches[2]]; // [longitude, latitude]
        }
        return null;
    }

    private function getEmptyDashboard(DashboardResponseDTO $response): DashboardResponseDTO
    {
        $response->kpis = [
            'totalTasks' => 0,
            'completionRate' => '0%',
            'avgTaskDuration' => '0h 00m',
            'avgDistancePerAgent' => '0.0 km',
            'totalAlerts' => 0,
            'subscription' => 'Inactif'
        ];

        $response->charts = [
            'tasksOverTime' => ['type' => 'line', 'labels' => [], 'datasets' => []],
            'taskCompletion' => ['type' => 'doughnut', 'labels' => [], 'datasets' => []],
            'agentPunctuality' => ['type' => 'bar', 'labels' => [], 'datasets' => []],
            'averageResponseTime' => ['type' => 'bar', 'labels' => [], 'datasets' => []]
        ];

        return $response;
    }
}