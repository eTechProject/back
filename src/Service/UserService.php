<?php

namespace App\Service;

use App\Dto\RegisterRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository
    ) {}

    public function createUserFromRequest(RegisterRequest $request): User
    {
        if ($this->userRepository->findOneByEmail($request->email)) {
            throw new \InvalidArgumentException('Email already exists');
        }

        $user = new User();
        $user->setName($request->name);
        $user->setEmail($request->email);
        $user->setPhone($request->phone);
        $user->setRole('ROLE_USER');

        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $request->password
        );
        $user->setPassword($hashedPassword);

        return $user;
    }
}
