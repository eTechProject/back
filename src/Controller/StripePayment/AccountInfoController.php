<?php

namespace App\Controller\StripePayment;

use App\DTO\Payment\StripePayment\Response\StripeAccountInfoResponseDTO;
use App\Repository\StripePaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/stripe-payment/account-info', name: 'api_stripe_account_info', methods: ['GET'])]
class AccountInfoController extends AbstractController
{
    public function __construct(
        private StripePaymentRepository $stripePaymentRepository
    ) {}

    public function __invoke(): JsonResponse
    {
        try {
            $responseDto = $this->stripePaymentRepository->getAccountInfo();
            
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
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
