<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher)
    {
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
    }

    public function createUser(array $data): User
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Le champ "name" est obligatoire.');
        }
        if (empty($data['email'])) {
            throw new \InvalidArgumentException('Le champ "email" est obligatoire.');
        }
        if (empty($data['password'])) {
            throw new \InvalidArgumentException('Le champ "password" est obligatoire.');
        }

        $user = new User();
        $user->setName($data['name']);
        $user->setEmail($data['email']);
        $user->setRole('ROLE_USER');

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $user->setCreatedAt(new \DateTime());

        if (!empty($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        return $user;
    }
}
