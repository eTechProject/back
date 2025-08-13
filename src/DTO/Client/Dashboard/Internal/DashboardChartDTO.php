<?php

namespace App\DTO\Client\Dashboard\Internal;

class DashboardChartDTO
{
    public string $type;
    public array $data;
    // type: ex 'tasks', 'performance', 'incidents', 'activity', 'financial'
}
