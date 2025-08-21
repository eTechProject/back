<?php

namespace App\Controller\Client;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Client\DashboardService;
use App\DTO\Dashboard\Request\DashboardFiltersDTO;
use App\Service\CryptService;
use App\Enum\EntityType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;


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

            // Vérification stricte : seul le client connecté peut accéder à son dashboard
            $user = $this->getUser();
            if (!$user || !in_array('ROLE_CLIENT', $user->getRoles(), true) || (method_exists($user, 'getId') && $user->getId() !== $clientId)) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Accès refusé : seul le client concerné peut accéder à ce dashboard',
                ], 403);
            }

            $filters = new DashboardFiltersDTO();
            $filters->dateRange = $request->query->get('dateRange', 'all');
            $filters->choice = $request->query->get('choice');
            $filters->dateStart = $request->query->get('dateStart');
            $filters->dateEnd = $request->query->get('dateEnd');

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
