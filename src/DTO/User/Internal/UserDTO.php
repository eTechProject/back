<?php 
namespace App\DTO\User\Internal;

use App\Enum\UserRole;

class UserDTO
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $name,
        public UserRole $role,
    ) {}
}