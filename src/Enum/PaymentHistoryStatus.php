<?php

namespace App\Enum;

enum PaymentHistoryStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case PENDING = 'pending';
}
