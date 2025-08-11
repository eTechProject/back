<?php

namespace App\Enum;

enum NotificationType: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
    case ASSIGNMENT = 'assignment';
    case MESSAGE = 'message';
    case TASK_UPDATE = 'task_update';
}
