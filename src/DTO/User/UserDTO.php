<?php 
namespace App\DTO\User;

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