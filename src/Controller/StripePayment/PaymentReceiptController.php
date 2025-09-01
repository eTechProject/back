<?php

namespace App\Controller\StripePayment;

use App\DTO\Payment\StripePayment\Response\StripePaymentReceiptResponseDTO;
use App\DTO\Payment\StripePayment\Internal\StripePaymentMethodDTO;
use App\DTO\Payment\StripePayment\Internal\StripeFormattedReceiptDTO;
use App\Repository\StripePaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/stripe-payment/{transactionId}/receipt', name: 'api_stripe_payment_receipt', methods: ['GET'])]
class PaymentReceiptController extends AbstractController
{
    public function __construct(
        private StripePaymentRepository $stripePaymentRepository
    ) {}

    public function __invoke(string $transactionId): JsonResponse
    {
        try {
            $payment = $this->stripePaymentRepository->findPaymentById($transactionId);
            
            $formattedDto = new StripeFormattedReceiptDTO(
                amountDisplay: number_format($payment->amount, 2) . ' ' . strtoupper($payment->currency),
                date: date('Y-m-d H:i:s', $payment->created),
                statusDisplay: ucfirst($payment->status)
            );

            $receiptDto = new StripePaymentReceiptResponseDTO(
                transactionId: $payment->id,
                amount: $payment->amount,
                currency: $payment->currency,
                status: $payment->status,
                description: $payment->description,
                created: $payment->created,
                receiptUrl: $payment->receiptUrl,
                paymentMethod: $payment->paymentMethod,
                formatted: $formattedDto
            );

            return new JsonResponse([
                'success' => true,
                'data' => $receiptDto,
                'message' => 'Receipt URL: ' . ($payment->receiptUrl ?? 'No receipt URL available'),
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
