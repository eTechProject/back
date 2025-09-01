<?php

namespace App\DTO\Payment;

class PaymentResponseDTO
{
    public string $id;
    public PackResponseDTO $pack;
    public string $status;
    public string $startDate;
    public ?string $endDate;
    public string $createdAt;
    public string $updatedAt;
}

class PackResponseDTO
{
    public string $id;
    public string $description;
    public int $nbAgents;
    public string $price;
}
