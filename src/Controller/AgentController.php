<?php

namespace App\Controller;

use App\DTO\Agent\RegisterAgentDTO;
use App\DTO\Agent\AgentProfileDTO;
use App\Service\AgentService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/public/agents')]
class AgentController extends AbstractController
{
    public function __construct(private AgentService $agentService) {}

    /**
     * Créer un agent
     */
    #[Route('', name: 'api_agents_create', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $dto = new RegisterAgentDTO($data);
        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        try {
            $agent = $this->agentService->createAgent($dto);
            return $this->json($agent, 201, [], ['groups' => ['agent:read']]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lister tous les agents
     */
    #[Route('', name: 'api_agents_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->agentService->getAllAgents(), 200, [], ['groups' => ['agent:read']]);
    }

    /**
     * Afficher un agent
     */
    #[Route('/{id}/num', name: 'api_agents_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $agent = $this->agentService->getAgent($id);

        if (!$agent) {
            return $this->json(['message' => 'Agent not found'], 404);
        }

        return $this->json($agent, 200, [], ['groups' => ['agent:read']]);
    }

    /**
     * Récupérer le profil sécurisé d'un agent
     */
    #[Route('/{id}', name: 'api_agents_profile', methods: ['GET'])]
    public function getProfile(int $id): JsonResponse
    {
        $agent = $this->agentService->getAgent($id); // ou getAgentById si tu préfères
        if (!$agent) {
            return $this->json(['message' => 'Agent not found'], 404);
        }
        $dto = $this->agentService->getAgentProfile($agent);
        return $this->json($dto);
    }

    /**
     * Mettre à jour un agent
     */
    #[Route('/{id}', name: 'api_agents_update', methods: ['PUT'])]
    public function update(int $id, Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $dto = new AgentProfileDTO($data);
        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        try {
            $agent = $this->agentService->updateAgent($id, $dto);

            if (!$agent) {
                return $this->json(['message' => 'Agent not found'], 404);
            }

            return $this->json($agent, 200, [], ['groups' => ['agent:read']]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer un agent
     */
    #[Route('/{id}', name: 'api_agents_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $deleted = $this->agentService->deleteAgent($id);

        if (!$deleted) {
            return $this->json(['message' => 'Agent not found'], 404);
        }

        return $this->json(['deleted' => true]);
    }
}