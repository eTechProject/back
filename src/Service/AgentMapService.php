<?php

namespace App\Service;

use App\DTO\Agent\Internal\AssignedTaskMapDTO;
use App\DTO\Agent\Response\AgentMapDataDTO;
use App\DTO\Agent\Response\AgentPositionHistoryDTO;
use App\DTO\Client\Internal\ClientInfoDTO;
use App\DTO\Client\Internal\AgentPositionDTO;
use App\DTO\Client\Internal\AssignedAgentDTO;
use App\DTO\Client\Internal\TaskAssignmentDTO;
use App\Entity\Tasks;
use App\Enum\EntityType;
use App\Repository\AgentLocationSignificantRepository;
use App\Repository\AgentLocationsRawRepository;
use App\Repository\AgentsRepository;
use App\Repository\TasksRepository;

class AgentMapService
{
    public function __construct(
        private SecuredZoneService $securedZoneService,
        private AgentService $agentService,
        private CryptService $cryptService,
        private TasksRepository $tasksRepository,
        private AgentLocationSignificantRepository $agentLocationSignificantRepository,
        private AgentLocationsRawRepository $agentLocationsRawRepository,
        private AgentsRepository $agentsRepository,
    ) {}

    /**
     * Get agent map data for the current assigned task of a specific agent
     * 
     * @param string $userIdCrypt The encrypted user ID (linked to the agent)
     * @return AgentMapDataDTO|null
     */
    public function getAgentMapData(string $userIdCrypt): ?AgentMapDataDTO
    {
        // Decrypt the user ID and find the associated agent
        $userId = $this->cryptService->decryptId($userIdCrypt, EntityType::USER->value);
        $agent = $this->agentsRepository->findOneBy(['user' => $userId]);
        
        if (!$agent) {
            return null;
        }

        // Get the current assigned task for this agent (IN_PROGRESS status)
        $task = $this->tasksRepository->findOneBy([
            'agent' => $agent,
            'status' => \App\Enum\Status::PENDING
        ]);
        
        if (!$task) {
            return null;
        }

        return $this->buildAgentMapDataDTO($task);
    }

    private function buildAgentMapDataDTO(Tasks $task): AgentMapDataDTO
    {
        $serviceOrder = $task->getOrder();        

        // Build assigned task DTO (now called serviceOrder to match ClientMap structure)
        $serviceOrderDTO = $this->buildAssignedTaskMapDTO($task);
        
        // Build secured zone DTO
        $securedZoneDTO = $this->securedZoneService->toDTO($serviceOrder->getSecuredZone());

        // Build assigned agents DTOs (all agents working on the same service order)
        $assignedAgentsDTO = $this->buildAssignedAgentsDTO($serviceOrder);

        return new AgentMapDataDTO(
            $serviceOrderDTO,
            $securedZoneDTO,
            $assignedAgentsDTO
        );
    }

    private function buildAssignedTaskMapDTO(Tasks $task): AssignedTaskMapDTO
    {
        $serviceOrder = $task->getOrder();
        $client = $serviceOrder->getClient();

        $clientDTO = new ClientInfoDTO(
            $this->cryptService->encryptId($client->getId(), EntityType::USER->value),
            $client->getName()
        );

        return new AssignedTaskMapDTO(
            $this->cryptService->encryptId($serviceOrder->getId(), EntityType::SERVICE_ORDER->value),
            $serviceOrder->getDescription(),
            $task->getStatus()->value,
            $serviceOrder->getCreatedAt(),
            $clientDTO
        );
    }

    /**
     * @return AssignedAgentDTO[]
     */
    private function buildAssignedAgentsDTO($serviceOrder): array
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
            ['recordedAt' => 'DESC']
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