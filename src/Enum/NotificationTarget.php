<?php

namespace App\Enum;

enum NotificationTarget: string
{
    case USER = 'user';
    case AGENT = 'agent';
    case ADMIN = 'admin';
    case ALL = 'all';
}
