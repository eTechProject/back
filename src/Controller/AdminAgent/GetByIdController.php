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
#[Route('/api/admin/agents/{encryptedId}', name: 'api_admin_agents_get_by_id', methods: ['GET'], requirements: ['encryptedId' => '^(?!search$).+'])]
class GetByIdController extends AbstractController
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
            $agent = $this->agentService->getAgent($id);

            if (!$agent) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Agent non trouvé'
                ], 404);
            }

            $dto = $this->agentService->getAgentProfile($agent);

            return $this->json([
                'status' => 'success',
                'data' => $dto
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de l\'agent',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}