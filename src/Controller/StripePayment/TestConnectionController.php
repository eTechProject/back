<?php

namespace App\Controller\StripePayment;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/stripe-payment/test-connection', name: 'api_stripe_test_connection', methods: ['GET'])]
class TestConnectionController extends AbstractController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => [
                'message' => 'Stripe payment service is ready',
                'environment' => 'test',
                'provider' => 'Stripe',
                'version' => '1.0'
            ],
            'timestamp' => time()
        ], Response::HTTP_OK);
    }
}
