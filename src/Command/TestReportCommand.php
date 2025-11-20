<?php

namespace App\Command;

use App\Service\AIReportService;
use App\Service\TaskService;
use Doctrine\ORM\EntityManagerInterface;
use App\Enum\Status;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-report',
    description: 'Test AI report generation for a completed task',
)]
class TestReportCommand extends Command
{
    public function __construct(
        private readonly AIReportService $aiReportService,
        private readonly TaskService $taskService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('task-id', InputArgument::OPTIONAL, 'Task ID to generate report for');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🧪 Test de génération de rapport IA');

        // Get task ID from argument or find a completed task
        $taskId = $input->getArgument('task-id');
        
        if ($taskId) {
            $task = $this->entityManager->getRepository('App\Entity\Tasks')->find($taskId);
            
            if (!$task) {
                $io->error("Tâche #$taskId non trouvée");
                return Command::FAILURE;
            }
        } else {
            // Find the most recent completed task
            $tasks = $this->entityManager->getRepository('App\Entity\Tasks')
                ->findBy(['status' => Status::COMPLETED], ['id' => 'DESC'], 1);
            
            if (empty($tasks)) {
                $io->error('Aucune tâche complétée trouvée pour le test');
                $io->note('Créez d\'abord une tâche complétée avec des données de test');
                return Command::FAILURE;
            }
            
            $task = $tasks[0];
        }

        // Display task information
        $io->section('📋 Informations de la tâche');
        $io->table(
            ['Propriété', 'Valeur'],
            [
                ['ID', $task->getId()],
                ['Description', $task->getDescription()],
                ['Type', $task->getType()->value],
                ['Status', $task->getStatus()->value],
                ['Agent', $task->getAgent()->getUser()->getName()],
                ['Client', $task->getOrder()->getClient()->getName()],
                ['Date début', $task->getStartDate()->format('d/m/Y H:i:s')],
                ['Date fin', $task->getEndDate()?->format('d/m/Y H:i:s') ?? 'Non terminée'],
            ]
        );

        if ($task->getStatus() !== Status::COMPLETED) {
            $io->warning('⚠️  Cette tâche n\'est pas complétée. Le rapport ne peut être généré que pour les tâches terminées.');
            return Command::FAILURE;
        }

        // Generate report
        $io->section('⏳ Génération du rapport');
        $io->text('Appel de l\'API Google Gemini AI...');
        
        try {
            $startTime = microtime(true);
            $result = $this->aiReportService->generateTaskReport($task);
            $endTime = microtime(true);
            
            $duration = round($endTime - $startTime, 2);
            
            $io->success("✅ Rapport généré avec succès en {$duration}s");
            
            // Display the report
            $io->section('📄 RAPPORT GÉNÉRÉ');
            $io->block($result['data']['report'], null, 'fg=white;bg=blue', ' ', true);
            
            // Display metadata
            $io->section('📊 Métadonnées');
            $io->table(
                ['Propriété', 'Valeur'],
                [
                    ['Task ID', $result['data']['task_id']],
                    ['Generated at', $result['data']['generated_at']],
                    ['Duration', $duration . 's'],
                    ['Report length', strlen($result['data']['report']) . ' caractères'],
                ]
            );
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('❌ Erreur lors de la génération du rapport');
            $io->text($e->getMessage());
            
            if ($output->isVerbose()) {
                $io->section('Stack trace');
                $io->text($e->getTraceAsString());
            }
            
            return Command::FAILURE;
        }
    }
}
