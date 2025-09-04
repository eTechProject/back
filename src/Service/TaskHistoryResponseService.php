<?php

namespace App\Service;

use App\Entity\Agents;
use App\Enum\EntityType;
use App\Enum\Status;
use App\DTO\Dashboard\Request\DashboardFiltersDTO;
use \App\Repository\AgentLocationsArchiveRepository;
use App\Repository\AgentLocationsRawRepository;

class TaskHistoryResponseService
{
    public function __construct(
        private TaskService $taskService,
        private CryptService $cryptService,
        private AgentLocationArchiveService $agentLocationArchiveService,
        private AgentLocationsArchiveRepository $agentLocationsArchiveRepository,
        private AgentLocationsRawRepository $agentLocationsRawRepository
    ) {}
    
    /**
     * Construit la réponse complète pour l'historique des tâches d'un agent
     */
    public function buildTaskHistoryAgentResponse(
        Agents $agent, 
        int $page, 
        int $limit, 
        ?Status $statusFilter = null,
        ?string $encryptedAgentId = null,
        ?DashboardFiltersDTO $filters = null
    ): array {
        if ($filters) {
            [$tasks, $total] = $this->taskService->getFilteredTasksHistoryByAgent($agent, $page, $limit, $statusFilter, $filters);
        } else {
            [$tasks, $total] = $this->taskService->getTasksHistoryByAgent($agent, $page, $limit, $statusFilter);
        }
        
        // Convertir en DTOs
        $taskDTOs = [];
        foreach ($tasks as $task) {
            $taskDTOs[] = $this->taskService->taskToHistoryDTO($task);
        }

        $pages = (int) ceil($total / $limit);
        
        $agentId = $encryptedAgentId ?? $this->cryptService->encryptId($agent->getId(), EntityType::AGENT->value);

        // Get all tasks for the agent (without pagination) for statistics and charts
        $allAgentTasks = $this->taskService->getTasksByAgent($agent);
        if ($filters) {
            $allAgentTasks = $this->filterTasksByDate($allAgentTasks, $filters);
        }

        // Calculate task statistics
        $taskStats = $this->calculateKPIs($allAgentTasks);

        // Build charts
        $charts = [
            'tasksOverTime' => $this->buildTasksOverTimeChart($allAgentTasks),
            'taskCompletion' => $this->buildTaskCompletionChart($allAgentTasks)
        ];

        // Informations sur l'agent avec ses tâches
        $agentInfo = [
            'agentId' => $agentId,
            'name' => $agent->getUser()->getName(),
            'email' => $agent->getUser()->getEmail(),
            'tasks' => $taskDTOs
        ];

        return [
            'status' => 'success',
            'message' => 'Historique des tâches récupéré avec succès',
            'data' => $agentInfo,
            'filters' => [
                'choice' => $filters?->choice,
                'dateStart' => $filters?->dateStart,
                'dateEnd' => $filters?->dateEnd,
            ],
            'kpis' => $taskStats,
            'charts' => $charts,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }
    public function buildTaskHistoryResponse(
        Agents $agent, 
        int $page, 
        int $limit, 
        ?Status $statusFilter = null,
        ?string $encryptedAgentId = null,
        ?DashboardFiltersDTO $filters = null
    ): array {
        if ($filters) {
            [$tasks, $total] = $this->taskService->getFilteredTasksHistoryByAgent($agent, $page, $limit, $statusFilter, $filters);
        } else {
            [$tasks, $total] = $this->taskService->getTasksHistoryByAgent($agent, $page, $limit, $statusFilter);
        }
        
        // Convertir en DTOs
        $taskDTOs = [];
        foreach ($tasks as $task) {
            $taskDTOs[] = $this->taskService->taskToHistoryDTO($task);
        }

        $pages = (int) ceil($total / $limit);
        
        $agentId = $encryptedAgentId ?? $this->cryptService->encryptId($agent->getId(), EntityType::AGENT->value);


        // Informations sur l'agent avec ses tâches
        $agentInfo = [
            'agentId' => $agentId,
            'name' => $agent->getUser()->getName(),
            'email' => $agent->getUser()->getEmail(),
            'tasks' => $taskDTOs
        ];

        return [
            'status' => 'success',
            'message' => 'Historique des tâches récupéré avec succès',
            'data' => $agentInfo,
            'filters' => [
                'choice' => $filters?->choice,
                'dateStart' => $filters?->dateStart,
                'dateEnd' => $filters?->dateEnd,
            ],
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * Filter tasks by date based on DashboardFiltersDTO
     */
    private function filterTasksByDate(array $tasks, DashboardFiltersDTO $filters): array
    {
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
                $filterStart = new \DateTimeImmutable($filters->dateStart);
                if ($start < $filterStart) return false;
            }
            if ($filters->dateEnd) {
                $filterEnd = new \DateTimeImmutable($filters->dateEnd);
                $filterEnd = $filterEnd->setTime(23, 59, 59);
                if ($start > $filterEnd) return false;
            }

            return true;
        });
    }

    private function calculateKPIs(array $tasks): array
    {
        $totalTasks = count($tasks);
        $completedTasks = 0;
        $totalDuration = 0;
        $validDurationTasks = 0;

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
        $totalDistance = $this->calculateTotalDistance($tasks);

        // Format average duration
        $avgDuration = $validDurationTasks > 0 ? $totalDuration / $validDurationTasks : 0;
        $avgDurationFormatted = $this->formatDuration($avgDuration);

        // Completion rate
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;


        return [
            'totalTasks' => $totalTasks,
            'completionRate' => $completionRate . '%',
            'avgTaskDuration' => $avgDurationFormatted,
            'avgDistance' => round($totalDistance, 1) . ' km',
            'agentPunctuality' => $this->calculateAgentPunctuality($tasks) . '%'
        ];
    }
    private function calculateAgentPunctuality(array $tasks): float
    {
        $agentPunctuality = ['total' => 0, 'punctual' => 0];

        foreach ($tasks as $task) {
            if (!method_exists($task, 'getAgent') || !method_exists($task, 'getStartDate')) {
                continue;
            }

            $agentPunctuality['total']++;

            // Check punctuality using first agent location
            $isPunctual = $this->isTaskPunctual($task);
            if ($isPunctual) {
                $agentPunctuality['punctual']++;
            }
        }

        return $agentPunctuality['total'] > 0 
            ? round(($agentPunctuality['punctual'] / $agentPunctuality['total']) * 100, 1) 
            : 0;
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
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%dh %02dm', $hours, $minutes);
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
    /**
     * Build tasks over time chart data
     */
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

    /**
     * Build task completion chart data
     */
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
}
