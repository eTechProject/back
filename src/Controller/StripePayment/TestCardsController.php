<?php

namespace App\Controller\StripePayment;

use App\DTO\Payment\StripePayment\Response\StripeTestCardsResponseDTO;
use App\DTO\Payment\StripePayment\Internal\StripeTestCardDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/stripe-payment/test-cards', name: 'api_stripe_test_cards', methods: ['GET'])]
class TestCardsController extends AbstractController
{
    public function __invoke(): JsonResponse
    {
        $testCards = [
            new StripeTestCardDTO(
                type: 'Visa Success',
                number: '4242424242424242',
                expiry: '12/25',
                cvv: '123',
                description: 'Carte de test qui réussira'
            ),
            new StripeTestCardDTO(
                type: 'Visa Decline',
                number: '4000000000000002',
                expiry: '12/25',
                cvv: '123',
                description: 'Carte de test qui sera déclinée'
            ),
            new StripeTestCardDTO(
                type: 'Mastercard Success',
                number: '5555555555554444',
                expiry: '12/25',
                cvv: '123',
                description: 'Mastercard de test qui réussira'
            ),
            new StripeTestCardDTO(
                type: 'Amex Success',
                number: '378282246310005',
                expiry: '12/25',
                cvv: '1234',
                description: 'American Express de test'
            )
        ];

        $responseDto = new StripeTestCardsResponseDTO($testCards);

        return new JsonResponse([
            'success' => true,
            'data' => $responseDto,
            'timestamp' => time()
        ], Response::HTTP_OK);
    }
}
