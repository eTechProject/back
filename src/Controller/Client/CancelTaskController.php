<?php

namespace App\Controller\Client;

use App\Service\TaskService;
use App\Enum\EntityType;
use App\Enum\Status;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public/client/task/cancel', name: 'api_client_cancel_task', methods: ['POST'])]
class CancelTaskController extends AbstractController
{
    public function __construct(
        private readonly TaskService $taskService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $encryptedTaskId = $data['taskId'] ?? null;

        if (!$encryptedTaskId) {
            return $this->json([
                'status' => 'error',
                'message' => 'Paramètre taskId manquant.'
            ], 400);
        }

        try {
            $task = $this->taskService->getTaskByEncryptedId($encryptedTaskId);
            if (!$task) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Tâche non trouvée.'
                ], 404);
            }
            $task->setStatus(Status::CANCELLED);
            $this->taskService->saveTask($task);
            return $this->json([
                'status' => 'success',
                'message' => 'Tâche annulée avec succès.'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'annulation de la tâche.'
            ], 500);
        }
    }
}
