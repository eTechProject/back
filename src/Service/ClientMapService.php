<?php

namespace App\Service;

use App\DTO\Client\Internal\AgentPositionDTO;
use App\DTO\Client\Internal\AssignedAgentDTO;
use App\DTO\Client\Internal\ClientInfoDTO;
use App\DTO\Client\Response\ClientMapDataDTO;
use App\DTO\Client\Internal\ServiceOrderMapDTO;
use App\DTO\Client\Internal\TaskAssignmentDTO;
use App\Entity\ServiceOrders;
use App\Entity\Tasks;
use App\Enum\EntityType;
use App\Enum\Reason;
use App\Enum\Status;
use App\Repository\AgentLocationSignificantRepository;
use App\Repository\AgentLocationsRawRepository;
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
        private AgentLocationsRawRepository $agentLocationsRawRepository,
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

        // Get agent's most recent raw position
        $agentRawLocation = $this->agentLocationsRawRepository->findOneBy(
            ['agent' => $task->getAgent()],
            ['recorded_at' => 'DESC']
        );

        // Determine status based on whether we have a recent position
        $status = $agentRawLocation ? 'actif' : 'inactif';
        $currentPosition = $agentRawLocation ? $this->buildAgentPositionFromRaw($agentRawLocation) : null;

        // Build task assignment DTO
        $taskDTO = $this->buildTaskAssignmentDTO($task);

        return new AssignedAgentDTO(
            $this->cryptService->encryptId($task->getId(), EntityType::TASK->value),
            $status,
            $agentDTO,
            $taskDTO,
            $currentPosition
        );
    }

    private function buildAgentPositionFromRaw($agentRawLocation): AgentPositionDTO
    {
        // Extract coordinates from Point geometry
        $geom = $agentRawLocation->getGeom();
        $longitude = 0.0;
        $latitude = 0.0;

        if (preg_match('/POINT\(([^ ]+) ([^ ]+)\)/', $geom, $matches)) {
            $longitude = (float)$matches[1];
            $latitude = (float)$matches[2];
        }

        return new AgentPositionDTO(
            $longitude,
            $latitude,
            $agentRawLocation->getRecordedAt(),
            'current'
        );
    }

    private function buildTaskAssignmentDTO(Tasks $task): TaskAssignmentDTO
    {
        // Extract task assign position coordinates
        $assignPositionGeom = $task->getAssignPosition();
        $assignPosition = [0.0, 0.0]; // Default coordinates [longitude, latitude]
        
        if (preg_match('/POINT\(([^ ]+) ([^ ]+)\)/', $assignPositionGeom, $matches)) {
            $assignPosition = [(float)$matches[1], (float)$matches[2]];
        }

        return new TaskAssignmentDTO(
            $this->cryptService->encryptId($task->getId(), EntityType::TASK->value),
            $task->getStatus()->value,
            $task->getDescription(),
            $task->getStartDate(),
            $task->getEndDate(),
            $assignPosition
        );
    }
}
