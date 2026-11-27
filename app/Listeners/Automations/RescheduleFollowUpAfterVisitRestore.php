<?php

namespace App\Listeners\Automations;

use App\Events\Visits\VisitRestored;

class RescheduleFollowUpAfterVisitRestore
{
    public function __construct(public ScheduleFollowUpAfterVisit $scheduler) {}

    public function handle(VisitRestored $event): void
    {
        $this->scheduler->schedule($event->visit);
    }
}
