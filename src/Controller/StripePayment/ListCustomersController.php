<?php

namespace App\Controller\StripePayment;

use App\Repository\StripePaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/stripe-payment/customers', name: 'api_stripe_customers_list', methods: ['GET'])]
class ListCustomersController extends AbstractController
{
    public function __construct(
        private StripePaymentRepository $stripePaymentRepository
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $limit = max(1, min(100, (int)$request->query->get('limit', 10)));
            $customers = $this->stripePaymentRepository->findAllCustomers($limit);

            return new JsonResponse([
                'success' => true,
                'data' => $customers,
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
