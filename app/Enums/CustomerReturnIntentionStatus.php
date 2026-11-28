<?php

namespace App\Enums;

enum CustomerReturnIntentionStatus: string
{
    case Expected = 'expected';
    case Arrived = 'arrived';
    case Missed = 'missed';
}
