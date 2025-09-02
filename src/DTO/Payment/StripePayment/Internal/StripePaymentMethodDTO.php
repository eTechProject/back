<?php

namespace App\DTO\Payment\StripePayment\Internal;

class StripePaymentMethodDTO
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $brand = null,
        public readonly ?string $last4 = null,
        public readonly ?string $country = null,
    ) {}
}
