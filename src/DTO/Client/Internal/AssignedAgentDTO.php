<?php

namespace App\DTO\Client\Internal;

use App\DTO\Agent\Response\AgentResponseDTO;

class AssignedAgentDTO
{
    public function __construct(
        public string $id,
        public string $status,
        public AgentResponseDTO $agent,
        public TaskAssignmentDTO $task,
        public ?AgentPositionDTO $currentPosition,
    ) {}
}
