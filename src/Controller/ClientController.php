<?php

namespace App\Controller;

use App\Service\AgentService;
use App\Service\TaskService;
use App\Service\ClientMapService;
use App\DTO\ServiceOrder\AssignAgentsDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/client', name: 'api_client_')]
class ClientController extends AbstractController
{
    public function __construct(
        private AgentService $agentService,
        private TaskService $taskService,
        private ClientMapService $clientMapService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('/available-agents', name: 'available_agents', methods: ['GET'])]
    public function getAvailableAgents(): JsonResponse
    {
        try {
            $availableAgents = $this->agentService->getAvailableAgents();
            
            return $this->json([
                'status' => 'success',
                'data' => $availableAgents,
                'message' => 'Agents disponibles récupérés avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des agents disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/assign-agents', name: 'assign_agents', methods: ['POST'])]
    public function assignAgents(Request $request): JsonResponse
    {
        try {
            // Deserialize the request data into DTO
            $assignAgentsDTO = $this->serializer->deserialize(
                $request->getContent(),
                AssignAgentsDTO::class,
                'json'
            );

            // Validate the DTO
            $errors = $this->validator->validate($assignAgentsDTO);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                
                return $this->json([
                    'status' => 'error',
                    'message' => 'Données invalides',
                    'errors' => $errorMessages
                ], 400);
            }

            // Assign agents to the service order
            $tasks = $this->taskService->assignAgentsToOrder(
                $assignAgentsDTO->orderId,
                $assignAgentsDTO->agentAssignments
            );

            return $this->json([
                'status' => 'success',
                'data' => [
                    'tasksCreated' => count($tasks),
                    'message' => sprintf('%d agent(s) assigné(s) avec succès à la mission', count($tasks))
                ],
                'message' => 'Agents assignés avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'assignation des agents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/map-data', name: 'map_data', methods: ['GET'])]
    public function getClientMapData(): JsonResponse
    {
        try {
            $mapData = $this->clientMapService->getClientMapData();

            return $this->json([
                'success' => true,
                'data' => $mapData,
                'message' => 'Données de la carte client récupérées avec succès',
                'timestamp' => (new \DateTimeImmutable())->format('c')
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données de la carte',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
