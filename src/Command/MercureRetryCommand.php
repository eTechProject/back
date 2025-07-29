<?php

namespace App\Command;

use App\Service\MercureQueueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:mercure:retry-failed',
    description: 'Réessaie la publication des messages Mercure qui ont échoué'
)]
class MercureRetryCommand extends Command
{
    public function __construct(
        private MercureQueueService $mercureQueueService,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Nombre maximum de messages à traiter',
                50
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        
        $output->writeln([
            'Réessai des publications Mercure en échec',
            '=======================================',
            '',
        ]);
        
        $output->writeln(sprintf('Traitement de %d messages maximum...', $limit));
        
        try {
            $processedCount = $this->mercureQueueService->processFailedMessages($limit);
            
            $output->writeln(sprintf('%d messages traités avec succès', $processedCount));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du traitement des messages Mercure en échec', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $output->writeln(sprintf('<error>Erreur: %s</error>', $e->getMessage()));
            
            return Command::FAILURE;
        }
    }
}
