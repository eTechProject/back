<?php

namespace App\Controller\Agent;

use App\Service\AgentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_AGENT')]
#[Route('/api/agent/me', name: 'api_agent_me', methods: ['GET'])]
class GetProfileController extends AbstractController
{
    public function __construct(private AgentService $agentService) {}

    public function __invoke(): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $agent = $this->agentService->findByUser($user);
            if (!$agent) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Aucun agent lié à cet utilisateur'
                ], 404);
            }

            $agentProfile = $this->agentService->getAgentProfile($agent);

            return $this->json([
                'status' => 'success',
                'data' => $agentProfile
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur interne du serveur: ' . $e->getMessage()
            ], 500);
        }
    }
}
