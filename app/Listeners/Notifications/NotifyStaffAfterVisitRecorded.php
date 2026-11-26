<?php

namespace App\Listeners\Notifications;

use App\Events\Visits\VisitCompleted;
use App\Models\Business;
use App\Notifications\VisitRecordedNotification;

class NotifyStaffAfterVisitRecorded
{
    public function handle(VisitCompleted $event): void
    {
        $business = Business::query()->find($event->visit->business_id);
        if ($business === null) {
            return;
        }

        $business->members()
            ->wherePivot('status', 'active')
            ->get()
            ->each(function ($member) use ($business, $event): void {
                if ((int) $member->getKey() === (int) $event->visit->recorded_by) {
                    return;
                }

                if (! $member->hasBusinessPermission((int) $business->getKey(), 'dashboard.view')) {
                    return;
                }

                $member->notify(new VisitRecordedNotification);
            });
    }
}
