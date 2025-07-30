<?php

namespace App\Service;

use App\DTO\Client\AgentPositionDTO;
use App\DTO\Client\AssignedAgentDTO;
use App\DTO\Client\ClientInfoDTO;
use App\DTO\Client\ClientMapDataDTO;
use App\DTO\Client\ServiceOrderMapDTO;
use App\DTO\Client\TaskAssignmentDTO;
use App\Entity\ServiceOrders;
use App\Entity\Tasks;
use App\Enum\EntityType;
use App\Enum\Reason;
use App\Enum\Status;
use App\Repository\AgentLocationSignificantRepository;
use App\Repository\TasksRepository;

class ClientMapService
{
    public function __construct(
        private ServiceOrderService $serviceOrderService,
        private SecuredZoneService $securedZoneService,
        private AgentService $agentService,
        private CryptService $cryptService,
        private TasksRepository $tasksRepository,
        private AgentLocationSignificantRepository $agentLocationSignificantRepository,
    ) {}

    /**
     * Get client map data for all IN_PROGRESS service orders
     * 
     * @return ClientMapDataDTO[]
     */
    public function getClientMapData(): array
    {
        $serviceOrders = $this->serviceOrderService->findAll();
        $inProgressOrders = array_filter($serviceOrders, function($order) {
            return $order->getStatus() === Status::IN_PROGRESS;
        });

        $mapData = [];
        foreach ($inProgressOrders as $serviceOrder) {
            $mapData[] = $this->buildClientMapDataDTO($serviceOrder);
        }

        return $mapData;
    }

    private function buildClientMapDataDTO(ServiceOrders $serviceOrder): ClientMapDataDTO
    {
        // Build service order DTO
        $serviceOrderDTO = $this->buildServiceOrderMapDTO($serviceOrder);
        
        // Build secured zone DTO
        $securedZoneDTO = $this->securedZoneService->toDTO($serviceOrder->getSecuredZone());
        
        // Build assigned agents DTOs
        $assignedAgentsDTO = $this->buildAssignedAgentsDTO($serviceOrder);

        return new ClientMapDataDTO(
            $serviceOrderDTO,
            $securedZoneDTO,
            $assignedAgentsDTO
        );
    }

    private function buildServiceOrderMapDTO(ServiceOrders $serviceOrder): ServiceOrderMapDTO
    {
        $clientInfo = new ClientInfoDTO(
            $this->cryptService->encryptId($serviceOrder->getClient()->getId(), EntityType::USER->value),
            $serviceOrder->getClient()->getName()
        );

        return new ServiceOrderMapDTO(
            $this->cryptService->encryptId($serviceOrder->getId(), EntityType::SERVICE_ORDER->value),
            $serviceOrder->getDescription(),
            $serviceOrder->getStatus()->value,
            $serviceOrder->getCreatedAt(),
            $clientInfo
        );
    }

    /**
     * @return AssignedAgentDTO[]
     */
    private function buildAssignedAgentsDTO(ServiceOrders $serviceOrder): array
    {
        $tasks = $this->tasksRepository->findBy(['order' => $serviceOrder]);
        $assignedAgents = [];

        foreach ($tasks as $task) {
            $assignedAgents[] = $this->buildAssignedAgentDTO($task);
        }

        return $assignedAgents;
    }

    private function buildAssignedAgentDTO(Tasks $task): AssignedAgentDTO
    {
        // Get agent profile
        $agentDTO = $this->agentService->getAgentProfile($task->getAgent());

        // Get agent current position and status
        $agentLocation = $this->agentLocationSignificantRepository->findOneBy([
            'agent' => $task->getAgent(),
            'task' => $task,
            'reason' => Reason::START_TASK
        ]);

        $status = $agentLocation ? 'actif' : 'inactif';
        $currentPosition = $agentLocation ? $this->buildAgentPositionDTO($agentLocation) : null;

        // Build task assignment DTO
        $taskDTO = $this->buildTaskAssignmentDTO($task);

        return new AssignedAgentDTO(
            $task->getId(),
            $status,
            $agentDTO,
            $taskDTO,
            $currentPosition
        );
    }

    private function buildAgentPositionDTO($agentLocation): AgentPositionDTO
    {
        // Extract coordinates from Point geometry
        $geom = $agentLocation->getGeom();
        $longitude = 0.0;
        $latitude = 0.0;

        if (preg_match('/POINT\(([^ ]+) ([^ ]+)\)/', $geom, $matches)) {
            $longitude = (float)$matches[1];
            $latitude = (float)$matches[2];
        }

        return new AgentPositionDTO(
            $longitude,
            $latitude,
            $agentLocation->getRecordedAt(),
            $agentLocation->getReason()->value
        );
    }

    private function buildTaskAssignmentDTO(Tasks $task): TaskAssignmentDTO
    {
        // Extract task assign position
        $assignPositionGeom = $task->getAssignPosition();
        $assignPosition = '';
        
        if (preg_match('/POINT\(([^ ]+) ([^ ]+)\)/', $assignPositionGeom, $matches)) {
            $assignPosition = "POINT({$matches[1]} {$matches[2]})";
        }

        return new TaskAssignmentDTO(
            $task->getId(),
            $task->getStatus()->value,
            $task->getDescription(),
            $task->getStartDate(),
            $task->getEndDate(),
            $assignPosition
        );
    }
}
