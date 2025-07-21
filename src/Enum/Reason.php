<?php
namespace App\Enum;

enum Reason: string
{
    case START_TASK = 'start_task';
    case END_TASK = 'end_task';
    case ZONE_ENTRY = 'zone_entry';
    case ZONE_EXIT = 'zone_exit';
    case MANUAL_REPORT = 'manual_report';
    case ANOMALY = 'anomaly';
    case LONG_STOP = 'long_stop';
    case OUT_OF_ZONE = 'out_of_zone';
}