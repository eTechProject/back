<?php

namespace App\DTO\Payment\StripePayment\Internal;

class StripePaymentResponseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly int $created,
        public readonly string $description,
        public readonly ?string $receiptUrl = null,
        public readonly ?StripePaymentMethodDTO $paymentMethod = null,
    ) {}
}
