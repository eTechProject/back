<?php

namespace App\Service;

use App\DTO\Agent\Internal\AssignedTaskMapDTO;
use App\DTO\Agent\Response\AgentMapDataDTO;
use App\DTO\Agent\Response\AgentPositionHistoryDTO;
use App\DTO\Agent\Response\SimpleClientDTO;
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
            'status' => \App\Enum\Status::IN_PROGRESS
        ]);
        
        if (!$task) {
            return null;
        }

        return $this->buildAgentMapDataDTO($task);
    }

    private function buildAgentMapDataDTO(Tasks $task): AgentMapDataDTO
    {
        // Build assigned task DTO
        $assignedTaskDTO = $this->buildAssignedTaskMapDTO($task);
        
        // Build secured zone DTO
        $securedZoneDTO = $this->securedZoneService->toDTO($task->getOrder()->getSecuredZone());
        
        // Build agent position history DTOs
        $positionHistoryDTO = $this->buildAgentPositionHistoryDTO($task->getAgent());

        return new AgentMapDataDTO(
            $assignedTaskDTO,
            $securedZoneDTO,
            $positionHistoryDTO
        );
    }

    private function buildAssignedTaskMapDTO(Tasks $task): AssignedTaskMapDTO
    {
        $serviceOrder = $task->getOrder();
        $client = $serviceOrder->getClient();

        $clientDTO = new SimpleClientDTO(
            $this->cryptService->encryptId($client->getId(), EntityType::USER->value),
            $client->getName(),
            $client->getEmail()
        );

        // Extract task assign position coordinates
        $assignPositionGeom = $task->getAssignPosition();
        $assignPosition = [0.0, 0.0]; // Default coordinates [longitude, latitude]
        
        if (preg_match('/POINT\(([^ ]+) ([^ ]+)\)/', $assignPositionGeom, $matches)) {
            $assignPosition = [(float)$matches[1], (float)$matches[2]];
        }

        return new AssignedTaskMapDTO(
            $this->cryptService->encryptId($task->getId(), EntityType::TASK->value),
            $this->cryptService->encryptId($serviceOrder->getId(), EntityType::SERVICE_ORDER->value),
            $task->getDescription(),
            $task->getStatus()->value,
            $task->getStartDate(),
            $task->getEndDate(),
            $assignPosition,
            $clientDTO
        );
    }

    /**
     * @return AgentPositionHistoryDTO[]
     */
    private function buildAgentPositionHistoryDTO($agent): array
    {
        // Get recent significant positions for the agent (last 24 hours)
        $recentPositions = $this->agentLocationSignificantRepository->findBy(
            ['agent' => $agent],
            ['recorded_at' => 'DESC'],
            20 // Limit to 20 most recent positions
        );

        $positionHistory = [];
        foreach ($recentPositions as $position) {
            $positionHistory[] = $this->buildAgentPositionFromSignificant($position);
        }

        return $positionHistory;
    }

    private function buildAgentPositionFromSignificant($agentSignificantLocation): AgentPositionHistoryDTO
    {
        // Extract coordinates from Point geometry
        $geom = $agentSignificantLocation->getGeom();
        $longitude = 0.0;
        $latitude = 0.0;

        if (preg_match('/POINT\(([^ ]+) ([^ ]+)\)/', $geom, $matches)) {
            $longitude = (float)$matches[1];
            $latitude = (float)$matches[2];
        }

        return new AgentPositionHistoryDTO(
            $longitude,
            $latitude,
            $agentSignificantLocation->getRecordedAt(),
            $agentSignificantLocation->getReason()->value
        );
    }
}
