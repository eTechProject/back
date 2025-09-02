<?php

namespace App\DTO\Payment\StripePayment\Response;

class StripeAccountInfoResponseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $country,
        public readonly string $defaultCurrency,
        public readonly bool $chargesEnabled,
        public readonly bool $detailsSubmitted,
        public readonly bool $payoutsEnabled,
    ) {}
}
