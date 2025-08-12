<?php

namespace App\Controller\Client;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Client\DashboardService;
use App\DTO\Client\Dashboard\Request\DashboardFiltersDTO;
use App\Service\CryptService;
use App\Enum\EntityType;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly CryptService $cryptService
    ) {}

    #[Route('/api/client/{encryptedId}/dashboard', name: 'client_dashboard', methods: ['GET'])]
    public function __invoke(string $encryptedId, Request $request): JsonResponse
    {
        try {
            // Décrypter l'id client
            $clientId = $this->cryptService->decryptId($encryptedId, EntityType::USER->value);

            // Vérification sécurité : l'utilisateur connecté doit être le client demandé
            $user = $this->getUser();
            if (!$user || (method_exists($user, 'getId') && $user->getId() !== $clientId)) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Accès refusé : client non autorisé',
                ], 403);
            }

            $filters = new DashboardFiltersDTO();
            // TODO: Hydrater $filters avec les paramètres de la requête si besoin

            $dashboardData = $this->dashboardService->getDashboardData($clientId, $filters);

            return $this->json([
                'status' => 'success',
                'data' => $dashboardData,
                'message' => 'Données dashboard récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération du dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
