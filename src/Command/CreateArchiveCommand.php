<?php

namespace App\Command;

use App\Entity\Agents;
use App\Entity\Tasks;
use App\Repository\AgentsRepository;
use App\Repository\TasksRepository;
use App\Service\AgentLocationArchiveService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-archive',
    description: 'Manually create a trajectory archive for a task'
)]
class CreateArchiveCommand extends Command
{
    public function __construct(
        private AgentLocationArchiveService $archiveService,
        private AgentsRepository $agentsRepository,
        private TasksRepository $tasksRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('agent_id', InputArgument::REQUIRED, 'Agent ID')
            ->addArgument('task_id', InputArgument::REQUIRED, 'Task ID')
            ->setHelp('This command allows you to manually create a trajectory archive for a completed task.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $agentId = (int) $input->getArgument('agent_id');
        $taskId = (int) $input->getArgument('task_id');

        $io->title('Creating Trajectory Archive');

        // Find agent
        $agent = $this->agentsRepository->find($agentId);
        if (!$agent) {
            $io->error("Agent with ID {$agentId} not found");
            return Command::FAILURE;
        }

        // Find task
        $task = $this->tasksRepository->find($taskId);
        if (!$task) {
            $io->error("Task with ID {$taskId} not found");
            return Command::FAILURE;
        }

        // Verify task belongs to agent
        if ($task->getAgent()->getId() !== $agent->getId()) {
            $io->error("Task {$taskId} does not belong to agent {$agentId}");
            return Command::FAILURE;
        }

        $io->info("Agent: {$agent->getUser()->getName()} (ID: {$agentId})");
        $io->info("Task: {$task->getDescription()} (ID: {$taskId})");

        // Check if archive already exists
        if ($this->archiveService->archiveExistsForTask($task)) {
            $io->warning('Archive already exists for this task');
            return Command::SUCCESS;
        }

        try {
            // Create archive
            $archive = $this->archiveService->createTaskArchive($agent, $task);

            if ($archive) {
                $io->success('Archive created successfully!');
                $io->table(['Property', 'Value'], [
                    ['Archive ID', $archive->getId()],
                    ['Point Count', $archive->getPointCount()],
                    ['Path Length', number_format($archive->getPathLength() ?? 0, 2) . ' meters'],
                    ['Average Speed', $archive->getAvgSpeed() ? number_format($archive->getAvgSpeed(), 2) . ' m/s' : 'N/A'],
                    ['Start Time', $archive->getStartTime()->format('Y-m-d H:i:s')],
                    ['End Time', $archive->getEndTime()->format('Y-m-d H:i:s')],
                ]);
            } else {
                $io->warning('No raw locations found - archive not created');
            }

        } catch (\Exception $e) {
            $io->error("Failed to create archive: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
