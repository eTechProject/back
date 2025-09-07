<?php

namespace App\Controller\Client;

use App\Service\AIReportService;
use App\Service\PDFReportService;
use App\Service\TaskService;
use App\Service\CryptService;
use App\Enum\EntityType;
use App\Enum\Status;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CLIENT')]
#[Route('/api/client/task/{encryptedTaskId}/generate-report', name: 'api_client_generate_task_report', methods: ['POST'])]
class GenerateTaskReportController extends AbstractController
{
    public function __construct(
        private readonly AIReportService $aiReportService,
        private readonly PDFReportService $pdfReportService,
        private readonly TaskService $taskService,
        private readonly CryptService $cryptService
    ) {}

    public function __invoke(string $encryptedTaskId): Response
    {
        try {
            // Get task by encrypted ID
            $task = $this->taskService->getTaskByEncryptedId($encryptedTaskId);
            
            if (!$task) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Tâche non trouvée'
                ], 404);
            }

            // Verify the task belongs to the authenticated client
            /** @var \App\Entity\User $currentUser */
            $currentUser = $this->getUser();
            if ($task->getOrder()->getClient()->getId() !== $currentUser->getId()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Accès non autorisé à cette tâche'
                ], 403);
            }

            // Check if task is completed
            if ($task->getStatus() !== Status::COMPLETED) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Le rapport ne peut être généré que pour les tâches terminées'
                ], 400);
            }

            // Generate the AI report
            $reportResult = $this->aiReportService->generateTaskReport($task);
            
            // Generate PDF from the AI report
            $pdfContent = $this->pdfReportService->generatePDFFromReport(
                $reportResult['data']['report'], 
                $task
            );
            
            // Generate filename
            $filename = $this->pdfReportService->generatePDFFilename($task);
            
            // Return PDF response
            $response = new Response($pdfContent);
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Content-Length', strlen($pdfContent));
            
            return $response;

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\RuntimeException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur inattendue lors de la génération du rapport'
            ], 500);
        }
    }
}
