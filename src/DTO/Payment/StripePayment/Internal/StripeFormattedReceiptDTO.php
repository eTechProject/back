<?php

namespace App\DTO\Payment\StripePayment\Internal;

class StripeFormattedReceiptDTO
{
    public function __construct(
        public readonly string $amountDisplay,
        public readonly string $date,
        public readonly string $statusDisplay,
    ) {}
}
