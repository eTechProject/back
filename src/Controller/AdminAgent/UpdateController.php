<?php

namespace App\Controller\AdminAgent;

use App\DTO\Agent\AgentProfileDTO;
use App\Service\AgentService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/agents/{encryptedId}', name: 'api_admin_agents_update', methods: ['PUT'])]
class UpdateController extends AbstractController
{
    public function __construct(private AgentService $agentService, private ?CryptService $cryptService = null) {}

    public function __invoke(string $encryptedId, Request $request, SerializerInterface $serializer, ValidatorInterface $validator): JsonResponse
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

        try {
            $dto = $serializer->deserialize($request->getContent(), AgentProfileDTO::class, 'json');
        } catch (\Exception) {
            return $this->json([
                'status' => 'error',
                'message' => 'Format JSON invalide',
                'errors' => ['Invalid JSON format']
            ], 400);
        }

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'status' => 'error',
                'message' => 'Échec de la validation',
                'errors' => $errorMessages
            ], 422);
        }

        try {
            $agent = $this->agentService->updateAgent($id, $dto);
            if (!$agent) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Agent non trouvé'
                ], 404);
            }
            $agentDto = $this->agentService->getAgentProfile($agent);
            return $this->json([
                'status' => 'success',
                'message' => 'Agent mis à jour avec succès',
                'data' => $agentDto
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur serveur : ' . $e->getMessage()
            ], 500);
        }
    }
}