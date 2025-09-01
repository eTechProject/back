<?php

namespace App\DTO\Payment\StripePayment\Response;

class StripeCustomerResponseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly ?string $name = null,
        public readonly ?string $phone = null,
        public readonly int $created,
    ) {}
}
