<?php

namespace App\DTO\Agent\Response;

use App\DTO\Agent\Internal\AssignedTaskMapDTO;
use App\DTO\Client\Internal\AssignedAgentDTO;
use App\DTO\SecuredZone\Response\SecuredZoneDTO;

class AgentMapDataDTO
{
    public function __construct(
        public AssignedTaskMapDTO $serviceOrder,
        public SecuredZoneDTO $securedZone,
        /** @var AssignedAgentDTO[] */
        public array $assignedAgents,
    ) {}
}