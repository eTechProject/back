<?php

namespace App\DTO\Client\Response;

use App\DTO\User\Internal\UserDTO;

class ClientWithOrdersDTO
{
    public function __construct(
        public UserDTO $client,
        public array $serviceOrders = []
    ) {}
}
