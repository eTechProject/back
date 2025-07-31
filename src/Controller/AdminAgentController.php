<?php

namespace App\Controller;

use App\DTO\Agent\RegisterAgentDTO;
use App\DTO\Agent\AgentProfileDTO;
use App\Service\AgentService;
use App\Service\CryptService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Enum\EntityType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/public/admin/agents', name: 'api_admin_agents_')]
class AdminAgentController extends AbstractController
{
    public function __construct(
        private AgentService $agentService,
        private ?CryptService $cryptService = null
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse {
        try {
            $dto = $serializer->deserialize($request->getContent(), RegisterAgentDTO::class, 'json');
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
            $agent = $this->agentService->createAgent($dto);
            $agentDto = $this->agentService->getAgentProfile($agent);

            return $this->json([
                'status' => 'success',
                'message' => 'Agent créé avec succès',
                'data' => $agentDto
            ], 201);
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur serveur : ' . $e->getMessage()
            ], 500);
        }
    }
#[Route('', name: 'list', methods: ['GET'])]
public function list(Request $request): JsonResponse
{
    // Récupérer page et limit, valeurs par défaut : page=1, limit=20
    $page = max(1, (int) $request->query->get('page', 1));
    $limit = max(1, min(100, (int) $request->query->get('limit', 20))); // limiter à 100 max

    // Récupérer la liste paginée et le total
    [$agents, $total] = $this->agentService->getAgentsPaginated($page, $limit);

    // Transformer en DTO
    $agentDtos = [];
    foreach ($agents as $agent) {
        $agentDtos[] = $this->agentService->getAgentProfile($agent);
    }

    // Calculer le nombre total de pages
    $pages = (int) ceil($total / $limit);

    return $this->json([
        'status' => 'success',
        'data' => $agentDtos,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
    ]);
}


    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        try {
            $name = $request->query->get('name');
            $status = $request->query->get('status');

            $agents = $this->agentService->searchAgents($name, $status);

            $agentDtos = [];
            foreach ($agents as $agent) {
                $agentDtos[] = $this->agentService->getAgentProfile($agent);
            }

            return $this->json([
                'status' => 'success',
                'data' => $agentDtos,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de l\'agent',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{encryptedId}', name: 'get_by_id', methods: ['GET'])]
    public function getProfile(string $encryptedId): JsonResponse
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

    #[Route('/{encryptedId}', name: 'update', methods: ['PUT'])]
    public function update(
        string $encryptedId,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse {
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

    #[Route('/{encryptedId}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $encryptedId): JsonResponse
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
