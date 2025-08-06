<?php

namespace App\Controller\AdminAgent;

use App\Service\AgentService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/agents/{encryptedId}', name: 'api_admin_agents_delete', methods: ['DELETE'])]
class DeleteController extends AbstractController
{
    public function __construct(private AgentService $agentService, private ?CryptService $cryptService = null) {}

    public function __invoke(string $encryptedId): JsonResponse
    {
        if ($this->cryptService === null) {
            return $this->json([
                'status' => 'error',
                'message' => 'Service de décryptage non configuré'
            ], 500);
        }

        try {
            $id = $this->cryptService->decryptId($encryptedId, EntityType::AGENT->value);
        } catch (\Exception) {
            return $this->json([
                'status' => 'error',
                'message' => 'ID invalide'
            ], 400);
        }

        $agent = $this->agentService->getAgent($id);

        if (!$agent) {
            return $this->json([
                'status' => 'error',
                'message' => 'Agent non trouvé'
            ], 404);
        }

        $role = $agent->getUser()?->getRole()?->value;
        $deleted = $this->agentService->deleteAgent($id);

        if (!$deleted) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }

        return $this->json([
            'status' => 'success',
            'message' => 'Agent et utilisateur supprimés',
            'deletedUserRole' => $role
        ]);
    }
}