<?php
namespace App\Enum;

enum Genre: string
{
    case M = 'M';
    case F = 'F';

    public static function values(): array
    {
        return array_map(fn(self $g) => $g->value, self::cases());
    }
}
