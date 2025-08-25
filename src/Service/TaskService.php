<?php

namespace App\Service;

use App\Entity\Tasks;
use App\Entity\ServiceOrders;
use App\Entity\Agents;
use App\Enum\Status;
use App\Enum\TaskType;
use App\Enum\EntityType;
use App\Repository\TasksRepository;
use App\Repository\ServiceOrdersRepository;
use App\Repository\AgentsRepository;
use App\DTO\Task\Response\TaskHistoryDTO;
use Doctrine\ORM\EntityManagerInterface;

class TaskService
{
    public function __construct(
        private TasksRepository $tasksRepository,
        private ServiceOrdersRepository $serviceOrdersRepository,
        private AgentsRepository $agentsRepository,
        private CryptService $cryptService,
        private EntityManagerInterface $entityManager
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
        foreach ($agentAssignments as $index => $taskDto) {
            if (!is_object($taskDto) || !property_exists($taskDto, 'agentId')) {
                throw new \InvalidArgumentException("Assignation #{$index}: doit être une instance de TaskRequestDTO valide");
            }
            $agent = $this->validateAndGetAgent($taskDto->agentId);
            $this->validateAgentAvailability($agent);
            if (!isset($taskDto->assignPosition) || !is_array($taskDto->assignPosition) || count($taskDto->assignPosition) !== 2 || !is_numeric($taskDto->assignPosition[0]) || !is_numeric($taskDto->assignPosition[1])) {
                throw new \InvalidArgumentException("Assignation #{$index}: assignPosition doit être un tableau [longitude, latitude] de deux valeurs numériques");
            }
            $validatedAssignments[] = [
                'agent' => $agent,
                'type' => $taskDto->type,
                'description' => $taskDto->description,
                'startDate' => $taskDto->startDate,
                'endDate' => $taskDto->endDate,
                'assignPosition' => $taskDto->assignPosition
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
                $tasks[] = $task;
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
        // assignPosition is an array [lng, lat]
        $pointWKT = $this->createPointWKTFromCoordinates($assignment['assignPosition']);
        $task->setAssignPosition($pointWKT);
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
     * Convert a Task entity to TaskHistoryDTO
     */
    public function taskToHistoryDTO(Tasks $task): TaskHistoryDTO
    {
        return new TaskHistoryDTO(
            taskId: $this->cryptService->encryptId((string)$task->getId(), EntityType::TASK->value),
            description: $task->getDescription(),
            status: $task->getStatus()->value,
            type: $task->getType()->value,
            startDate: $task->getStartDate()->format('Y-m-d\TH:i:s\Z'),
            endDate: $task->getEndDate()?->format('Y-m-d\TH:i:s\Z'),
            orderId: $this->cryptService->encryptId((string)$task->getOrder()->getId(), EntityType::SERVICE_ORDER->value),
            orderDescription: $task->getOrder()->getDescription() ?? 'Ordre de service'
        );
    }
}
