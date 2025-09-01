<?php

namespace App\DTO\Payment\StripePayment\Response;

use App\DTO\Payment\StripePayment\Internal\StripeTestCardDTO;

class StripeTestCardsResponseDTO
{
    public function __construct(
        /** @var StripeTestCardDTO[] */
        public readonly array $testCards,
    ) {}
}
