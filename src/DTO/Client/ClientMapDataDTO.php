<?php

namespace App\DTO\Client;

use App\DTO\SecuredZone\SecuredZoneDTO;

class ClientMapDataDTO
{
    public function __construct(
        public ServiceOrderMapDTO $serviceOrder,
        public SecuredZoneDTO $securedZone,
        /** @var AssignedAgentDTO[] */
        public array $assignedAgents,
    ) {}
}
