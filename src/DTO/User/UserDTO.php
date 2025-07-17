<?php 
namespace App\DTO\User;

use App\Enum\UserRole;

class UserDTO
{
    public function __construct(
        public string $encryptedId,
        public string $email,
        public string $name,
        public UserRole $role,
    ) {}
}