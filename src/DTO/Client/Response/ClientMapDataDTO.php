<?php

namespace App\DTO\Client\Response;

use App\DTO\Client\Internal\ServiceOrderMapDTO;
use App\DTO\Client\Internal\AssignedAgentDTO;
use App\DTO\SecuredZone\Response\SecuredZoneDTO;

class ClientMapDataDTO
{
    public function __construct(
        public ServiceOrderMapDTO $serviceOrder,
        public SecuredZoneDTO $securedZone,
        /** @var AssignedAgentDTO[] */
        public array $assignedAgents,
    ) {}
}
