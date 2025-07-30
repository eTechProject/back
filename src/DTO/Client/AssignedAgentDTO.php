<?php

namespace App\DTO\Client;

use App\DTO\Agent\AgentResponseDTO;

class AssignedAgentDTO
{
    public function __construct(
        public int $id,
        public string $status,
        public AgentResponseDTO $agent,
        public TaskAssignmentDTO $task,
        public ?AgentPositionDTO $currentPosition,
    ) {}
}
