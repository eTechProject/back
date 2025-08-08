<?php

namespace App\Controller\Agent;

use App\Service\AgentService;
use App\Service\CryptService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_AGENT')]
#[Route('/api/agent/{encryptedId}', name: 'api_agent_get_profile', methods: ['GET'])]
class GetProfileController extends AbstractController
{
    public function __construct(private AgentService $agentService, private CryptService $cryptService) {}

    public function __invoke(string $encryptedId): JsonResponse
    {
        try {
            // Récupérer l'utilisateur connecté
            $user = $this->getUser();
            if (!$user) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Valider l'accès à l'agent
            $agent = $this->agentService->validateAgentAccess($encryptedId, $user);
            if (!$agent) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Agent non trouvé ou accès non autorisé'
                ], 404);
            }

            // Récupérer le profil de l'agent
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
