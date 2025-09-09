<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin-user',
    description: 'Creates an admin user with specified credentials',
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Admin user details
        $email = 'pokaneliot@gmail.com';
        $password = 'Admin123!';
        $name = 'anel';
        $role = UserRole::ADMIN;

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        
        if ($existingUser) {
            $io->warning(sprintf('User with email "%s" already exists!', $email));
            return Command::FAILURE;
        }

        // Create new admin user
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setRole($role);
        
        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Persist and flush
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Admin user created successfully!'));
        $io->table(
            ['Field', 'Value'],
            [
                ['Name', $user->getName()],
                ['Email', $user->getEmail()],
                ['Role', $user->getRole()->value],
                ['Created At', $user->getCreatedAt()->format('Y-m-d H:i:s')],
            ]
        );

        return Command::SUCCESS;
    }
}
