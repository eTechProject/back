<?php

namespace App\Enum;

enum EntityType: string
{
    case USER = 'user';
    case SECURED_ZONE = 'secured_zone';
    case SERVICE_ORDER = 'service_order';
    case AGENT = 'agent';
    case TASK = 'task';
    case MESSAGE = 'message';
    case NOTIFICATION = 'notification';
    case PACK = 'pack';
    case PAYMENT = 'payment';
    case PAYMENT_HISTORY = 'payment_history';
}
