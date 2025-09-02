<?php

namespace App\Repository;

use App\DTO\Payment\StripePayment\Response\StripePaymentResponseDTO;
use App\DTO\Payment\StripePayment\Response\StripeCustomerResponseDTO;
use App\DTO\Payment\StripePayment\Response\StripeAccountInfoResponseDTO;
use App\Service\PaymentService;
use App\Service\StripePaymentService;
use App\Entity\User;

class StripePaymentRepository
{
    public function __construct(
        private StripePaymentService $stripePaymentService
    ) {}

    public function createPayment(User $user, string $packId, array $paymentData): StripePaymentResponseDTO
    {
        return $this->stripePaymentService->processPayment($user, $packId, $paymentData);
    }

    public function findPaymentById(string $transactionId): StripePaymentResponseDTO
    {
        return $this->stripePaymentService->getPayment($transactionId);
    }

    public function createCustomer(array $customerData): StripeCustomerResponseDTO
    {
        return $this->stripePaymentService->createCustomer($customerData);
    }

    /**
     * @return StripePaymentResponseDTO[]
     */
    public function findAllPayments(int $limit = 10): array
    {
        return $this->stripePaymentService->listPayments($limit);
    }

    /**
     * @return StripeCustomerResponseDTO[]
     */
    public function findAllCustomers(int $limit = 10): array
    {
        return $this->stripePaymentService->listCustomers($limit);
    }

    public function getAccountInfo(): StripeAccountInfoResponseDTO
    {
        return $this->stripePaymentService->getAccountInfo();
    }
}
