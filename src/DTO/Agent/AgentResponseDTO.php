<?php

namespace App\DTO\Agent;

use App\DTO\User\UserDTO;
use App\Enum\Genre;

class AgentResponseDTO
{
    public function __construct(
        public string $encryptedId,
        public ?string $address,
        public Genre $sexe,
        public ?string $profilePictureUrl,
        public UserDTO $user,
    ) {}
}
