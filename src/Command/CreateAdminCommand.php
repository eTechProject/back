<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create a super admin user',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Admin email')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Admin password', 'Admin123!')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Admin name', 'Super Admin')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getOption('password');
        $name = $input->getOption('name');

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        
        if ($existingUser) {
            $io->warning(sprintf('User with email "%s" already exists.', $email));
            return Command::SUCCESS;
        }

        // Create new admin user
        $admin = new User();
        $admin->setEmail($email);
        $admin->setName($name);
        $admin->setRole(UserRole::ADMIN);
        
        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($admin, $password);
        $admin->setPassword($hashedPassword);

        // Persist to database
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Super admin created successfully with email: %s', $email));
        $io->note(sprintf('Default password: %s', $password));
        $io->warning('Please change the default password after first login!');

        return Command::SUCCESS;
    }
}
