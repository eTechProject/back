<?php

namespace App\DTO\Agent\Response;

use App\DTO\Agent\Internal\AssignedTaskMapDTO;
use App\DTO\SecuredZone\Response\SecuredZoneDTO;

class AgentMapDataDTO
{
    public function __construct(
        public AssignedTaskMapDTO $assignedTask,
        public SecuredZoneDTO $securedZone,
        /** @var AgentPositionHistoryDTO[] */
        public array $positionHistory,
    ) {}
}
