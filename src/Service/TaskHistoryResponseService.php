<?php

namespace App\Service;

use App\Entity\Agents;
use App\Enum\EntityType;

class TaskHistoryResponseService
{
    public function __construct(
        private TaskService $taskService,
        private CryptService $cryptService
    ) {}
    
    /**
     * Construit la réponse complète pour l'historique des tâches d'un agent
     */
    public function buildTaskHistoryResponse(
        Agents $agent, 
        int $page, 
        int $limit, 
        ?\App\Enum\Status $statusFilter = null,
        ?string $encryptedAgentId = null
    ): array {
        // Récupérer l'historique des tâches avec pagination
        [$tasks, $total] = $this->taskService->getTasksHistoryByAgent($agent, $page, $limit, $statusFilter);
        
        // Convertir en DTOs
        $taskDTOs = [];
        foreach ($tasks as $task) {
            $taskDTOs[] = $this->taskService->taskToHistoryDTO($task);
        }

        $pages = (int) ceil($total / $limit);
        
        // Utiliser l'ID chiffré fourni ou le générer
        $agentId = $encryptedAgentId ?? $this->cryptService->encryptId($agent->getId(), EntityType::AGENT->value);

        // Informations sur l'agent avec ses tâches
        $agentInfo = [
            'agentId' => $agentId,
            'name' => $agent->getUser()->getName(),
            'email' => $agent->getUser()->getEmail(),
            'tasks' => $taskDTOs
        ];

        return [
            'status' => 'success',
            'message' => 'Historique des tâches récupéré avec succès',
            'agent' => $agentInfo,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $pages,
                'status_filter' => $statusFilter?->value
            ]
        ];
    }
}
