<?php

namespace App\Enum;

enum AlertType: string
{
    case INCIDENT = 'incident';
    case DANGER = 'danger';
    case URGENCE = 'urgence';

    public static function values(): array
{
    return array_map(fn(self $case) => $case->value, self::cases());
}

}