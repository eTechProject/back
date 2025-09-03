<?php

namespace App\Controller\Client;

use App\Service\ClientPaymentHistoryService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/client/{id}/payment-history', name: 'api_client_payment_history', methods: ['GET'])]
#[IsGranted('ROLE_CLIENT')]
class GetClientPaymentHistoryController extends AbstractController
{
    public function __construct(
        private readonly ClientPaymentHistoryService $clientPaymentHistoryService,
        private readonly CryptService $cryptService
    ) {}

    public function __invoke(string $id, Request $request): JsonResponse
    {
        try {
            $clientId = $this->cryptService->decryptId($id, EntityType::USER->value);
        } catch (\Exception) {
            return $this->json([
                'status' => 'error',
                'message' => 'Identifiant client invalide'
            ], 400);
        }

        $user = $this->getUser();
        if (!$user || $user->getId() !== $clientId) {
            return $this->json([
                'status' => 'error',
                'message' => 'Accès refusé'
            ], 403);
        }

        // Paramètres de pagination et filtres
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = max(1, min(50, (int)$request->query->get('limit', 10))); // Limite max de 50, défaut 10
        $statusFilter = $request->query->get('status');
        $providerFilter = $request->query->get('provider');
        $startDateFilter = $request->query->get('start_date');
        $endDateFilter = $request->query->get('end_date');

        // Validation des filtres optionnels
        if ($statusFilter && !in_array($statusFilter, ['pending', 'completed', 'failed', 'cancelled', 'refunded'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Statut invalide. Valeurs autorisées: pending, completed, failed, cancelled, refunded'
            ], 400);
        }

        if ($providerFilter && !in_array($providerFilter, ['stripe', 'cybersource', 'paypal'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Provider invalide. Valeurs autorisées: stripe, cybersource, paypal'
            ], 400);
        }

        try {
            $responseData = $this->clientPaymentHistoryService->getClientPaymentHistory(
                $user,
                $page,
                $limit,
                $statusFilter,
                $providerFilter,
                $startDateFilter,
                $endDateFilter
            );

            return $this->json([
                'status' => 'success',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de l\'historique des paiements: ' . $e->getMessage()
            ], 500);
        }
    }
}
