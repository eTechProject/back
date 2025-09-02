<?php

namespace App\DTO\Payment;

class InitiatePaymentResponseDTO
{
    public string $paymentId;
    public string $historyId;
    public string $provider;
    public array $session;
}
