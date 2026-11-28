<?php

namespace App\Listeners\Notifications;

use App\Events\Reviews\ReviewRecorded;
use App\Models\Business;
use App\Notifications\ReviewRecordedNotification;

class NotifyStaffAfterReviewRecorded
{
    public function handle(ReviewRecorded $event): void
    {
        $business = Business::query()->find($event->businessId);
        if ($business === null) {
            return;
        }

        $business->members()
            ->wherePivot('status', 'active')
            ->get()
            ->each(function ($member) use ($business, $event): void {
                if ((int) $member->getKey() === $event->actorId
                    || ! $member->hasBusinessPermission((int) $business->getKey(), 'reviews.view')) {
                    return;
                }

                $member->notify(new ReviewRecordedNotification);
            });
    }
}
