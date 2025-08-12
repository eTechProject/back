<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case ACTIF = 'actif';
    case NON_PAYE = 'non_paye';
    case EXPIRE = 'expire';
}
