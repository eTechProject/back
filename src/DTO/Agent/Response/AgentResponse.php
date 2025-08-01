<?php

namespace App\DTO\Agent\Response;

use App\DTO\User\Internal\UserDTO;
use App\Enum\Genre;

class AgentResponse
{
    public function __construct(
        public string $agentId,
        public ?string $address,
        public Genre $sexe,
        public ?string $profilePictureUrl,
        public UserDTO $user,
    ) {}
}
