<?php

namespace App\Command;

use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:encrypt-id',
    description: 'Encrypt an ID for a given entity type'
)]
class EncryptIdCommand extends Command
{
    public function __construct(
        private readonly CryptService $cryptService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'The ID to encrypt')
            ->addArgument('type', InputArgument::REQUIRED, 'The entity type (agent, task, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (int) $input->getArgument('id');
        $type = $input->getArgument('type');

        try {
            $encryptedId = $this->cryptService->encryptId($id, $type);
            
            $output->writeln("Original ID: {$id}");
            $output->writeln("Entity Type: {$type}");
            $output->writeln("Encrypted ID: {$encryptedId}");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
