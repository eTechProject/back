<?php

namespace App\DTO\Payment;

use Symfony\Component\Validator\Constraints as Assert;

class CreatePaymentDTO
{
    #[Assert\NotNull]
    public string|int|null $packId = null;

    public ?float $amount = null;

    public ?string $currency = null;
}
