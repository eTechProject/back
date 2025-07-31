<?php

namespace App\Controller\Client;

use App\DTO\ServiceOrder\AssignAgentsDTO;
use App\Service\TaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/client/assign-agents', name: 'api_client_assign_agents', methods: ['POST'])]
class AssignAgentsController extends AbstractController
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {}

    public function __invoke(Request $request): JsonResponse
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
}
