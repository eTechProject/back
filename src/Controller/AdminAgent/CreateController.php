<?php

namespace App\Controller\AdminAgent;

use App\DTO\Agent\RegisterAgentDTO;
use App\Service\AgentService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/api/public/admin/agents', name: 'api_admin_agents_create', methods: ['POST'])]
class CreateController extends AbstractController
{
    public function __construct(private AgentService $agentService) {}

    public function __invoke(Request $request, SerializerInterface $serializer, ValidatorInterface $validator): JsonResponse
    {
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
}
