<?php

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

#[AsCommand(
    name: 'app:queue:status',
    description: 'Monitor the status of message queues'
)]
class QueueStatusCommand extends Command
{
    public function __construct(
        private readonly TransportInterface $locationQueueTransport,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Monitor message queue status and health')
            ->addOption('interval', 'i', InputOption::VALUE_OPTIONAL, 'Refresh interval in seconds', 5)
            ->addOption('continuous', 'c', InputOption::VALUE_NONE, 'Run continuously with refresh interval')
            ->setHelp('This command allows you to monitor the status of message queues...');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $interval = (int) $input->getOption('interval');
        $continuous = $input->getOption('continuous');

        $io->title('Message Queue Status Monitor');

        do {
            $this->displayQueueStatus($io);
            
            if ($continuous) {
                sleep($interval);
                $io->write("\033[2J\033[H"); // Clear screen and move cursor to top
                $io->title('Message Queue Status Monitor (Refreshed)');
            }
        } while ($continuous);

        return Command::SUCCESS;
    }

    private function displayQueueStatus(SymfonyStyle $io): void
    {
        $io->section('Location Queue Status');

        try {
            if ($this->locationQueueTransport instanceof ReceiverInterface) {
                $messages = $this->locationQueueTransport->get();
                
                if (empty($messages)) {
                    $io->success('Queue is empty - all messages processed');
                } else {
                    $io->warning(sprintf('Queue contains %d pending messages', count($messages)));
                }
            } else {
                $io->note('Queue receiver interface not available');
            }

            $io->text([
                sprintf('Timestamp: %s', (new \DateTimeImmutable())->format('Y-m-d H:i:s')),
                'Queue: location_queue',
                'Transport: RabbitMQ AMQP'
            ]);

        } catch (\Exception $e) {
            $io->error(sprintf('Failed to check queue status: %s', $e->getMessage()));
            $this->logger->error('Queue status check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}