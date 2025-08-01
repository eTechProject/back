<?php

namespace App\Controller\Client;

use App\Service\ClientMapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/client/map-data', name: 'api_client_map_data', methods: ['GET'])]
class GetClientMapDataController extends AbstractController
{
    public function __construct(
        private readonly ClientMapService $clientMapService
    ) {}

    public function __invoke(): JsonResponse
    {
        try {
            $mapData = $this->clientMapService->getClientMapData();

            return $this->json([
                'success' => true,
                'data' => $mapData,
                'message' => 'Données de la carte client récupérées avec succès',
                'timestamp' => (new \DateTimeImmutable())->format('c')
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données de la carte',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
