<?php

namespace App\DTO\Client\Response;

class AlertResponseDTO
{
    public string $status;
    public int $alertId;
    public string $timestamp;

    public function __construct(string $status, int $alertId, string $timestamp)
    {
        $this->status = $status;
        $this->alertId = $alertId;
        $this->timestamp = $timestamp;
    }
}
