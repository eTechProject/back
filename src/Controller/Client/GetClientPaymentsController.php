<?php

namespace App\Controller\Client;

use App\Service\PaymentService;
use App\Service\CryptService;
use App\Enum\EntityType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/client/{id}/payment', name: 'api_client_payment', methods: ['GET'])]
#[IsGranted('ROLE_CLIENT')]
class GetClientPaymentsController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService,
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

        $page = max(1, (int)$request->query->get('page', 1));
        $limit = max(1, (int)$request->query->get('limit', 20));
        $statusFilter = $request->query->get('status');
        $startDateFilter = $request->query->get('start_date');
        $endDateFilter = $request->query->get('end_date');

        // Délègue toute la logique métier et mapping au service
        $responseData = $this->paymentService->getClientPaymentsResponse(
            $user,
            $clientId,
            $page,
            $limit,
            $statusFilter,
            $startDateFilter,
            $endDateFilter,
            $this->cryptService
        );

        return $this->json([
            'status' => 'success',
            'data' => $responseData
        ]);
    }
}
