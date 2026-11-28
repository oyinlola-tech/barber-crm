<?php

namespace App\Enums;

enum AutomationTrigger: string
{
    case VisitCompleted = 'visit_completed';
    case CustomerInactive = 'customer_inactive';
    case ReturnDateMissed = 'return_date_missed';
}
