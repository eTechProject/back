<?php

namespace App\DTO\Payment\StripePayment\Internal;

class StripeTestCardDTO
{
    public function __construct(
        public readonly string $type,
        public readonly string $number,
        public readonly string $expiry,
        public readonly string $cvv,
        public readonly string $description,
    ) {}
}
