<?php

namespace App\Controller\Agent;

use App\DTO\Agent\Request\UpdateAgentDTO;
use App\Service\AgentService;
use App\Service\CryptService;
use App\Entity\Agents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_AGENT')]
#[Route('/api/agent/{encryptedId}', name: 'api_agent_update_profile', methods: ['PUT'])]
class UpdateProfileController extends AbstractController
{
    public function __construct(private AgentService $agentService, private CryptService $cryptService) {}

    public function __invoke(string $encryptedId, Request $request, SerializerInterface $serializer, ValidatorInterface $validator): JsonResponse
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

            // Désérialiser le DTO
            $dto = $serializer->deserialize($request->getContent(), UpdateAgentDTO::class, 'json');

            // Valider le DTO
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

            // Mettre à jour l'agent
            $updatedAgent = $this->agentService->updateAgent($agent->getId(), $dto);

            if (!$updatedAgent) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Erreur lors de la mise à jour du profil'
                ], 500);
            }

            // Retourner le profil mis à jour
            $agentProfile = $this->agentService->getAgentProfile($updatedAgent);

            return $this->json([
                'status' => 'success',
                'message' => 'Profil mis à jour avec succès',
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
