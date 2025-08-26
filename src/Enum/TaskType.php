<?php

namespace App\Enum;

enum TaskType: string
{
    case PATROUILLE = 'patrouille';
    case INTERVENTION = 'intervention';
    case SURVEILLANCE = 'surveillance';
    case AUTRE = 'autre';
    // Add more types as needed
}
