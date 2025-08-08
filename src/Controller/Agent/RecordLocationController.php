<?php

namespace App\Controller\Agent;

use App\Service\AgentLocationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_AGENT')]
#[Route('/api/agent/{idcryptagent}/locations', name: 'api_agent_record_location', methods: ['POST'])]
class RecordLocationController extends AbstractController
{
    public function __construct(
        private readonly AgentLocationService $agentLocationService,
        private readonly \App\Service\CryptService $cryptService
    ) {}

    public function __invoke(string $idcryptagent, Request $request): JsonResponse
    {

        try {
            $response = $this->agentLocationService->processLocationRequest(
                $idcryptagent, 
                $request->getContent()
            );

            return $this->json($response, 201);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
            ], 400);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'enregistrement de la position',
                'error' => $e->getMessage(),
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
            ], 500);
        }
    }
}
