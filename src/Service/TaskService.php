<?php

namespace App\Service;

use App\Entity\Tasks;
use App\Entity\ServiceOrders;
use App\Entity\Agents;
use App\Enum\Status;
use App\Enum\TaskType;
use App\Enum\EntityType;
use App\Repository\TasksRepository;
use App\DTO\Task\Request\TaskRequestDTO;
use App\Repository\ServiceOrdersRepository;
use App\Repository\AgentsRepository;
use App\DTO\Task\Response\TaskHistoryDTO;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Notification\NotificationService;
use App\Enum\NotificationTarget;
use App\Enum\NotificationType;
use App\DTO\Dashboard\Request\DashboardFiltersDTO;
use App\Entity\User;

class TaskService
{
    public function __construct(
        private TasksRepository $tasksRepository,
        private ServiceOrdersRepository $serviceOrdersRepository,
        private AgentsRepository $agentsRepository,
        private CryptService $cryptService,
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService
    ) {}

    /**
     * Assign multiple agents to a service order with individual coordinates
     * 
     * @param string $encryptedOrderId The encrypted service order ID
     * @param array $agentAssignments Array of agent assignments with format: [['agentId' => 'encrypted_id', 'coordinates' => [lng, lat]], ...]
     * @return array Array of created tasks
     */
    public function assignAgentsToOrder(string $encryptedOrderId, array $agentAssignments): array
    {
        $serviceOrder = $this->validateAndGetServiceOrder($encryptedOrderId);
        $validatedAssignments = $this->validateAgentAssignments($agentAssignments);
        
        return $this->createTasksFromAssignments($serviceOrder, $validatedAssignments);
    }

    /**
     * Validate and retrieve the service order
     */
    private function validateAndGetServiceOrder(string $encryptedOrderId): ServiceOrders
    {
        $orderId = $this->cryptService->decryptId($encryptedOrderId, EntityType::SERVICE_ORDER->value);
        $serviceOrder = $this->serviceOrdersRepository->find($orderId);
        
        if (!$serviceOrder) {
            throw new \InvalidArgumentException('Ordre de service non trouvé');
        }

        return $serviceOrder;
    }

    /**
     * Validate all agent assignments and return validated data
     */
    private function validateAgentAssignments(array $agentAssignments): array
    {
        $validatedAssignments = [];

        foreach ($agentAssignments as $index => $assignment) {
            if (!is_array($assignment)) {
                throw new \InvalidArgumentException("Assignation #{$index}: doit être un tableau valide");
            }

            // Map array → DTO
            $taskDto = new TaskRequestDTO(
                $assignment['agentId'] ?? '',
                $assignment['type'] ?? '',
                $assignment['description'] ?? '',
                $assignment['startDate'] ?? '',
                $assignment['endDate'] ?? '',
                $assignment['assignPosition'] ?? []
            );

            // Validate agent existence & availability
            $agent = $this->validateAndGetAgent($taskDto->agentId);
            $this->validateAgentAvailability($agent);

            // Validate assignPosition
            if (
                !is_array($taskDto->assignPosition) ||
                count($taskDto->assignPosition) !== 2 ||
                !is_numeric($taskDto->assignPosition[0]) ||
                !is_numeric($taskDto->assignPosition[1])
            ) {
                throw new \InvalidArgumentException("Assignation #{$index}: assignPosition doit être un tableau [longitude, latitude] de deux valeurs numériques");
            }

            // Normalize validated data
            $validatedAssignments[] = [
                'agent'          => $agent,
                'type'           => $taskDto->type,
                'description'    => $taskDto->description,
                'startDate'      => $taskDto->startDate,
                'endDate'        => $taskDto->endDate,
                'assignPosition' => $taskDto->assignPosition,
            ];
        }

        return $validatedAssignments;
    }


    /**
     * Validate assignment structure (agentId and coordinates presence)
     */
    private function validateAssignmentStructure(array $assignment, int $index): void
    {
        if (!isset($assignment['agentId']) || !isset($assignment['coordinates'])) {
            throw new \InvalidArgumentException("Assignation d'agent #{$index}: agentId et coordinates sont requis");
        }
    }

    /**
     * Validate and retrieve agent by encrypted ID
     */
    private function validateAndGetAgent(string $encryptedAgentId): Agents
    {
        $agentId = $this->cryptService->decryptId($encryptedAgentId, EntityType::AGENT->value);
        $agent = $this->agentsRepository->find($agentId);
        
        if (!$agent) {
            throw new \InvalidArgumentException("Agent avec l'ID {$encryptedAgentId} non trouvé");
        }

        return $agent;
    }

    /**
     * Validate agent availability
     */
    private function validateAgentAvailability(Agents $agent): void
    {
        if (!$this->isAgentAvailable($agent)) {
            throw new \InvalidArgumentException("L'agent {$agent->getUser()->getName()} n'est pas disponible");
        }
    }

    /**
     * Validate coordinates format
     */
    private function validateCoordinates(array $coordinates, int $index): array
    {
        if (count($coordinates) !== 2 || !is_numeric($coordinates[0]) || !is_numeric($coordinates[1])) {
            throw new \InvalidArgumentException("Assignation d'agent #{$index}: les coordonnées doivent être un tableau avec [longitude, latitude]");
        }

        return $coordinates;
    }

    /**
     * Create tasks from validated assignments with transaction
     */
    private function createTasksFromAssignments(ServiceOrders $serviceOrder, array $validatedAssignments): array
    {
        $tasks = [];
        $this->entityManager->beginTransaction();

        try {
            foreach ($validatedAssignments as $assignment) {
                $task = $this->createTaskForAssignment($serviceOrder, $assignment);
                $this->entityManager->persist($task);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $tasks;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Create a single task for an agent assignment
     */
    private function createTaskForAssignment(ServiceOrders $serviceOrder, array $assignment): Tasks
    {
        $task = new Tasks();
        $task->setOrder($serviceOrder);
        $task->setAgent($assignment['agent']);
        $task->setStatus(Status::PENDING);
        $task->setType(TaskType::from($assignment['type']));
        $task->setDescription($assignment['description'] ?? '');
        $task->setStartDate(new \DateTimeImmutable($assignment['startDate']));
        $task->setEndDate(new \DateTimeImmutable($assignment['endDate']));
        $pointWKT = $this->createPointWKTFromCoordinates($assignment['assignPosition']);
        $task->setAssignPosition($pointWKT);
        
        // Format coordinates for notification message
        $positionText = "({$assignment['assignPosition'][0]}, {$assignment['assignPosition'][1]})";
        
        $this->notificationService->createNotification(
            "Nouvelle Mission Assignée",
            "Vous avez été assigné à une nouvelle mission à la position {$positionText} qui commence le {$assignment['startDate']} et se termine le {$assignment['endDate']}.",
            NotificationType::ASSIGNMENT,
            NotificationTarget::AGENT,
            $assignment['agent']->getUser(),
            true // reloadMap: trigger map reload on frontend
        );
        return $task;
    }

    /**
     * Create a WKT Point string from coordinates
     */
    private function createPointWKTFromCoordinates(array $coordinates): string
    {
        // WKT format for PostGIS Point: "POINT(longitude latitude)"
        return sprintf('POINT(%.6f %.6f)', (float)$coordinates[0], (float)$coordinates[1]);
    }

    /**
     * Check if an agent is available for assignment
     */
    private function isAgentAvailable(Agents $agent): bool
    {
        $activeTasks = $this->tasksRepository->findBy([
            'agent' => $agent,
            'status' => [Status::PENDING, Status::IN_PROGRESS]
        ]);

        return empty($activeTasks);
    }

    /**
     * Get tasks for a specific service order
     */
    public function getTasksByOrder(ServiceOrders $serviceOrder): array
    {
        return $this->tasksRepository->findBy(['order' => $serviceOrder]);
    }

    /**
     * Get tasks for a specific agent
     */
    public function getTasksByAgent(Agents $agent): array
    {
        return $this->tasksRepository->findBy(['agent' => $agent]);
    }

    /**
     * Get tasks history for a specific agent with pagination and optional status filter
     */
    public function getTasksHistoryByAgent(Agents $agent, int $page, int $limit, ?Status $statusFilter = null): array
    {
        $offset = ($page - 1) * $limit;
        
        $queryBuilder = $this->tasksRepository->createQueryBuilder('t')
            ->where('t.agent = :agent')
            ->setParameter('agent', $agent)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('t.startDate', 'DESC');

        if ($statusFilter) {
            $queryBuilder
                ->andWhere('t.status = :status')
                ->setParameter('status', $statusFilter);
        }

        $tasks = $queryBuilder->getQuery()->getResult();
        
        // Count total tasks for pagination
        $countQueryBuilder = $this->tasksRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.agent = :agent')
            ->setParameter('agent', $agent);

        if ($statusFilter) {
            $countQueryBuilder
                ->andWhere('t.status = :status')
                ->setParameter('status', $statusFilter);
        }

        $total = $countQueryBuilder->getQuery()->getSingleScalarResult();

        return [$tasks, $total];
    }

    /**
     * Get tasks history for a specific order with pagination, optional date filters
     */
    public function getFilteredTasksHistoryByOrder(ServiceOrders $order, int $page, int $limit, ?DashboardFiltersDTO $filters = null, ?string $statusFilter = null): array
    {
        $offset = ($page - 1) * $limit;
        
        $queryBuilder = $this->tasksRepository->createQueryBuilder('t')
            ->where('t.order = :order')
            ->setParameter('order', $order)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('t.startDate', 'DESC');

        if ($statusFilter) {
            $queryBuilder
                ->andWhere('t.status = :status')
                ->setParameter('status', $statusFilter);
        }

        // Apply date filters
        if ($filters) {
            $this->applyDateFiltersToQuery($queryBuilder, $filters);
        }

        $tasks = $queryBuilder->getQuery()->getResult();
        
        // Count total tasks for pagination
        $countQueryBuilder = $this->tasksRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.order = :order')
            ->setParameter('order', $order);

        if ($statusFilter) {
            $countQueryBuilder
                ->andWhere('t.status = :status')
                ->setParameter('status', $statusFilter);
        }

        // Apply same date filters to count query
        if ($filters) {
            $this->applyDateFiltersToQuery($countQueryBuilder, $filters);
        }

        $total = $countQueryBuilder->getQuery()->getSingleScalarResult();

        return [$tasks, $total];
    }

    /**
     * Apply date filters to query builder based on DashboardFiltersDTO
     */
    private function applyDateFiltersToQuery($queryBuilder, DashboardFiltersDTO $filters): void
    {
        $now = new \DateTimeImmutable();

        // Handle predefined date choices
        if ($filters->choice !== null) {
            switch ($filters->choice) {
                case 'today':
                    $startDate = $now->setTime(0, 0, 0);
                    $endDate = $now->setTime(23, 59, 59);
                    $queryBuilder->andWhere('t.startDate BETWEEN :startDate AND :endDate')
                        ->setParameter('startDate', $startDate)
                        ->setParameter('endDate', $endDate);
                    break;

                case 'last7days':
                    $startDate = $now->modify('-6 days')->setTime(0, 0, 0); // last 7 days including today
                    $endDate = $now->setTime(23, 59, 59);
                    $queryBuilder->andWhere('t.startDate BETWEEN :startDate AND :endDate')
                        ->setParameter('startDate', $startDate)
                        ->setParameter('endDate', $endDate);
                    break;

                case 'week':
                    $startDate = $now->modify('monday this week')->setTime(0, 0, 0);
                    $endDate = $now->modify('sunday this week')->setTime(23, 59, 59);
                    $queryBuilder->andWhere('t.startDate BETWEEN :startDate AND :endDate')
                        ->setParameter('startDate', $startDate)
                        ->setParameter('endDate', $endDate);
                    break;

                case 'thisMonth':
                    $startDate = $now->modify('first day of this month')->setTime(0, 0, 0);
                    $endDate = $now->modify('last day of this month')->setTime(23, 59, 59);
                    $queryBuilder->andWhere('t.startDate BETWEEN :startDate AND :endDate')
                        ->setParameter('startDate', $startDate)
                        ->setParameter('endDate', $endDate);
                    break;

                case 'last30days':
                    $startDate = $now->modify('-29 days')->setTime(0, 0, 0); // last 30 days including today
                    $endDate = $now->setTime(23, 59, 59);
                    $queryBuilder->andWhere('t.startDate BETWEEN :startDate AND :endDate')
                        ->setParameter('startDate', $startDate)
                        ->setParameter('endDate', $endDate);
                    break;

                case 'thisYear':
                    $startDate = $now->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0, 0);
                    $endDate = $now->setDate((int)$now->format('Y'), 12, 31)->setTime(23, 59, 59);
                    $queryBuilder->andWhere('t.startDate BETWEEN :startDate AND :endDate')
                        ->setParameter('startDate', $startDate)
                        ->setParameter('endDate', $endDate);
                    break;
            }
        } 
        // Handle custom date range
        elseif ($filters->dateStart !== null || $filters->dateEnd !== null) {
            if ($filters->dateStart !== null) {
                $startDate = new \DateTimeImmutable($filters->dateStart);
                $startDate = $startDate->setTime(0, 0, 0);
                $queryBuilder->andWhere('t.startDate >= :startDate')
                    ->setParameter('startDate', $startDate);
            }

            if ($filters->dateEnd !== null) {
                $endDate = new \DateTimeImmutable($filters->dateEnd);
                $endDate = $endDate->setTime(23, 59, 59);
                $queryBuilder->andWhere('t.startDate <= :endDate')
                    ->setParameter('endDate', $endDate);
            }
        }
    }


    /**
     * Get tasks history for a specific order with pagination
     */
    public function getTasksHistoryByOrder(ServiceOrders $order, int $page, int $limit, ?string $statusFilter = null): array
    {
        $offset = ($page - 1) * $limit;
        
        $queryBuilder = $this->tasksRepository->createQueryBuilder('t')
            ->where('t.order = :order')
            ->setParameter('order', $order)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('t.startDate', 'DESC');

        if ($statusFilter) {
            $queryBuilder
                ->andWhere('t.status = :status')
                ->setParameter('status', $statusFilter);
        }


        $tasks = $queryBuilder->getQuery()->getResult();
        
        // Count total tasks for pagination
        $countQueryBuilder = $this->tasksRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.order = :order')
            ->setParameter('order', $order);

        if ($statusFilter) {
            $countQueryBuilder
                ->andWhere('t.status = :status')
                ->setParameter('status', $statusFilter);
        }

        $total = $countQueryBuilder->getQuery()->getSingleScalarResult();

        return [$tasks, $total];
    }

    /**
     * Get tasks history for a specific agent with pagination, optional status filter and date filters
     */
    public function getFilteredTasksHistoryByAgent(Agents $agent, int $page, int $limit, ?Status $statusFilter = null, ?DashboardFiltersDTO $filters = null): array
    {
        $offset = ($page - 1) * $limit;
        
        $queryBuilder = $this->tasksRepository->createQueryBuilder('t')
            ->where('t.agent = :agent')
            ->setParameter('agent', $agent)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('t.startDate', 'DESC');

        if ($statusFilter) {
            $queryBuilder
                ->andWhere('t.status = :status')
                ->setParameter('status', $statusFilter);
        }

        // Apply date filters
        if ($filters) {
            $this->applyDateFiltersToQuery($queryBuilder, $filters);
        }

        $tasks = $queryBuilder->getQuery()->getResult();
        
        // Count total tasks for pagination
        $countQueryBuilder = $this->tasksRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.agent = :agent')
            ->setParameter('agent', $agent);

        if ($statusFilter) {
            $countQueryBuilder
                ->andWhere('t.status = :status')
                ->setParameter('status', $statusFilter);
        }

        // Apply same date filters to count query
        if ($filters) {
            $this->applyDateFiltersToQuery($countQueryBuilder, $filters);
        }

        $total = $countQueryBuilder->getQuery()->getSingleScalarResult();

        return [$tasks, $total];
    }


    /**
     * Convert a Task entity to TaskHistoryDTO
     */
    public function taskToHistoryDTO(Tasks $task): TaskHistoryDTO
    {
        // Extract coordinates from WKT format: "POINT(longitude latitude)" -> [longitude, latitude]
        $assignPosition = null;
        if ($task->getAssignPosition()) {
            $wktString = $task->getAssignPosition();
            if (preg_match('/POINT\(([+-]?\d*\.?\d+)\s+([+-]?\d*\.?\d+)\)/', $wktString, $matches)) {
                $assignPosition = [(float)$matches[1], (float)$matches[2]];
            }
        }

        return new TaskHistoryDTO(
            taskId: $this->cryptService->encryptId((string)$task->getId(), EntityType::TASK->value),
            description: $task->getDescription(),
            status: $task->getStatus()->value,
            type: $task->getType()->value,
            startDate: $task->getStartDate()->format('Y-m-d\TH:i:s\Z'),
            endDate: $task->getEndDate()?->format('Y-m-d\TH:i:s\Z'),
            orderId: $this->cryptService->encryptId((string)$task->getOrder()->getId(), EntityType::SERVICE_ORDER->value),
            orderDescription: $task->getOrder()->getDescription() ?? 'Ordre de service',
            assignPosition: $assignPosition,
            agentId: $this->cryptService->encryptId((string)$task->getAgent()->getId(), EntityType::AGENT->value),
            agentName: $task->getAgent()->getUser()->getName()
        );
    }
    public function getTaskByEncryptedId(string $encryptedTaskId): ?Tasks
    {
        $taskId = $this->cryptService->decryptId($encryptedTaskId, EntityType::TASK->value);
        return $this->tasksRepository->find($taskId);
    }

    /**
     * Save a Task entity (flush changes)
     */
    public function saveTask(Tasks $task): void
    {
        $this->entityManager->persist($task);
        $this->entityManager->flush();
        
        // Extract coordinates from WKT format for display
        $position = $task->getAssignPosition();
        $positionText = $position ? str_replace(['POINT(', ')'], ['(', ')'], $position) : 'position inconnue';
        
        $this->notificationService->createNotification(
            "Mission Annulée",
            "La mission à la position {$positionText} qui commençait le {$task->getStartDate()->format('Y-m-d H:i:s')} et se terminait le {$task->getEndDate()->format('Y-m-d H:i:s')} a été annulée.",
            NotificationType::ASSIGNMENT,
            NotificationTarget::AGENT,
            $task->getAgent()->getUser(),
            true // reloadMap: trigger map reload on frontend
        );
    }
}
