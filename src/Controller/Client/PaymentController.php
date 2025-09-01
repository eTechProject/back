<?php

namespace App\Controller\Client;

use App\Repository\UserRepository;
use App\Service\PaymentService;
use App\Service\CryptService;
use App\Enum\EntityType;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/api/client/{clientId}/payments', name: 'api_client_payments', methods: ['GET'])]
class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly UserRepository $userRepository,
        private readonly CryptService $cryptService
    ) {}

    public function __invoke(string $clientId): JsonResponse
    {
        try {
            // Decrypt the client ID
            $decryptedClientId = $this->cryptService->decryptId($clientId, EntityType::USER->value);
            
            // Verify client exists and is a client
            $client = $this->userRepository->find($decryptedClientId);
            if (!$client || $client->getRole() !== UserRole::CLIENT) {
                return $this->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            // Get all payments for this client using PaymentService
            $payments = $this->paymentService->getPaymentsByClient($decryptedClientId);

            // Convert payments to DTOs
            $paymentDTOs = $this->paymentService->convertArrayToDTO($payments);

            return $this->json([
                'success' => true,
                'data' => $paymentDTOs,
                'total' => count($paymentDTOs)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid client ID or error retrieving payments'
            ], 400);
        }
    }
}
