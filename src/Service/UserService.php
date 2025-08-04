<?php

namespace App\Service;

use App\DTO\User\Request\RegisterUserDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Enum\UserRole;
use App\Enum\EntityType;
use App\DTO\User\Internal\UserDTO;
use Doctrine\ORM\EntityManagerInterface;

class UserService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
        private CryptService $cryptService,
        private EntityManagerInterface $entityManager
    ) {}

    public function createUserFromRequest(RegisterUserDTO $request): User
    {
        if ($this->userRepository->findOneByEmail($request->email)) {
            throw new \InvalidArgumentException('Email already exists');
        }

        $user = new User();
        $user->setName($request->name);
        $user->setEmail($request->email);
        $user->setPhone($request->phone);

        $role = match (strtolower($request->role)) {
            'admin' => UserRole::ADMIN,
            'agent' => UserRole::AGENT,
            'client' => UserRole::CLIENT,
            default => UserRole::CLIENT,
        };
        $user->setRole($role);

        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $request->password
        );
        $user->setPassword($hashedPassword);

        // Persist the user within the service
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
    public function toDTO(User $user): UserDTO
    {
        return new UserDTO(
            userId: $this->cryptService->encryptId((string)$user->getId(), EntityType::USER->value),
            email: $user->getEmail(),
            name: $user->getName(),
            role: $user->getRole()
        );
    }
}
