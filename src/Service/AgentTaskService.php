<?php

namespace App\Service;

use App\Entity\Agents;
use App\Entity\User;
use App\Repository\AgentsRepository;
use App\Service\TaskService;
use App\Service\CryptService;
use App\Enum\EntityType;
use App\DTO\Agent\Response\SimpleAssignedTaskDTO;
use App\DTO\Agent\Response\SimpleClientDTO;

class AgentTaskService
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly CryptService $cryptService,
        private readonly AgentsRepository $agentsRepository
    ) {}

    /**
     * Get assigned tasks for an agent by encrypted agent ID
     */
    public function getAssignedTasksByEncryptedAgentId(string $encryptedAgentId): array
    {
        // Decrypt agent ID and find agent
        $agentId = $this->cryptService->decryptId($encryptedAgentId, EntityType::USER->value);
        $agent = $this->agentsRepository->find($agentId);
        
        if (!$agent) {
            throw new \InvalidArgumentException('Agent non trouvé');
        }

        return $this->getAssignedTasksForAgent($agent);
    }

    /**
     * Get assigned tasks for an agent by user entity
     */
    public function getAssignedTasksByUser(User $user): array
    {
        // Get the agent entity from the current user
        $agent = $this->agentsRepository->findOneBy(['user' => $user]);
        
        if (!$agent) {
            throw new \InvalidArgumentException('Agent non trouvé');
        }

        return $this->getAssignedTasksForAgent($agent);
    }

    /**
     * Get assigned tasks for a specific agent
     */
    private function getAssignedTasksForAgent(Agents $agent): array
    {
        // Get all tasks assigned to this agent
        $tasks = $this->taskService->getTasksByAgent($agent);

        // Transform tasks into response DTOs
        $tasksDTOs = [];
        foreach ($tasks as $task) {
            $tasksDTOs[] = $this->createAssignedTaskDTO($task);
        }

        return $tasksDTOs;
    }

    /**
     * Create SimpleAssignedTaskDTO from Task entity
     */
    private function createAssignedTaskDTO($task): SimpleAssignedTaskDTO
    {
        $order = $task->getOrder();
        $client = $order->getClient();

        // Create simplified client DTO
        $clientDTO = new SimpleClientDTO(
            $this->cryptService->encryptId($client->getId(), EntityType::USER->value),
            $client->getName(),
            $client->getEmail()
        );

        return new SimpleAssignedTaskDTO(
            $this->cryptService->encryptId($order->getId(), EntityType::SERVICE_ORDER->value),
            $task->getStatus()->value,
            $clientDTO
        );
    }
}
