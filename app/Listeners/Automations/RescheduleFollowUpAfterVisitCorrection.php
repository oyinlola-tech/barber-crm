<?php

namespace App\Listeners\Automations;

use App\Events\Visits\VisitCorrected;

class RescheduleFollowUpAfterVisitCorrection
{
    public function __construct(public ScheduleFollowUpAfterVisit $scheduler) {}

    public function handle(VisitCorrected $event): void
    {
        $this->scheduler->schedule($event->visit);
    }
}
