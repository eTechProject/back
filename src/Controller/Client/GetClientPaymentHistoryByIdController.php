<?php

namespace App\Controller\Client;

use App\Service\ClientPaymentHistoryService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/client/{id}/payment-history/{paymentId}', name: 'api_client_payment_history_by_id', methods: ['GET'])]
#[IsGranted('ROLE_CLIENT')]
class GetClientPaymentHistoryByIdController extends AbstractController
{
    public function __construct(
        private readonly ClientPaymentHistoryService $clientPaymentHistoryService,
        private readonly CryptService $cryptService
    ) {}

    public function __invoke(string $id, string $paymentId): JsonResponse
    {
        try {
            $clientId = $this->cryptService->decryptId($id, EntityType::USER->value);
        } catch (\Exception) {
            return $this->json([
                'status' => 'error',
                'message' => 'Identifiant client invalide'
            ], 400);
        }

        try {
            $paymentIdDecrypted = $this->cryptService->decryptId($paymentId, EntityType::PAYMENT->value);
        } catch (\Exception) {
            return $this->json([
                'status' => 'error',
                'message' => 'Identifiant de paiement invalide'
            ], 400);
        }

        $user = $this->getUser();
        if (!$user || $user->getId() !== $clientId) {
            return $this->json([
                'status' => 'error',
                'message' => 'Accès refusé'
            ], 403);
        }

        try {
            $paymentHistoryDetail = $this->clientPaymentHistoryService->getClientPaymentHistoryByPaymentId($user, $paymentIdDecrypted);

            if (!$paymentHistoryDetail) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Historique de paiement non trouvé'
                ], 404);
            }

            return $this->json([
                'status' => 'success',
                'data' => $paymentHistoryDetail
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération du paiement: ' . $e->getMessage()
            ], 500);
        }
    }
}
