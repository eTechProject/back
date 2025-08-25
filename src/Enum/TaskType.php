<?php

namespace App\Enum;

enum TaskType: string
{
    case PATROUILLE = 'patrouille';
    case INTERVENTION = 'intervention';
    case SURVEILLANCE = 'surveillance';
    // Add more types as needed
}
