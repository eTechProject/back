<?php

namespace App\DTO\Client\Dashboard\Internal;

class DashboardKpiDTO
{
    public int $tasks;
    public int $activeAgents;
    public float $duration;
    public float $distance;
    public int $incidents;
    public bool $subscription;
}
