<?php

namespace App\DTO\Payment\StripePayment\Response;

use App\DTO\Payment\StripePayment\Internal\StripePaymentMethodDTO;
use App\DTO\Payment\StripePayment\Internal\StripeFormattedReceiptDTO;

class StripePaymentReceiptResponseDTO
{
    public function __construct(
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly string $description,
        public readonly int $created,
        public readonly ?string $receiptUrl = null,
        public readonly ?StripePaymentMethodDTO $paymentMethod = null,
        public readonly StripeFormattedReceiptDTO $formatted,
    ) {}
}
