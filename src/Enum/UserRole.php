<?php

namespace App\Enum;

enum UserRole: string
{
    case ADMIN = 'admin';
    case AGENT = 'agent';
    case CLIENT = 'client';
}