<?php

namespace App\Controller\StripePayment;

use App\Repository\StripePaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/stripe-payment/{transactionId}', name: 'api_stripe_payment_get', methods: ['GET'])]
class GetPaymentController extends AbstractController
{
    public function __construct(
        private StripePaymentRepository $stripePaymentRepository
    ) {}

    public function __invoke(string $transactionId): JsonResponse
    {
        try {
            $responseDto = $this->stripePaymentRepository->findPaymentById($transactionId);
            
            return new JsonResponse([
                'success' => true,
                'data' => $responseDto,
                'timestamp' => time()
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
