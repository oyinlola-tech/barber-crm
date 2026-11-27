<?php

namespace App\Listeners\Notifications;

use App\Events\Customers\CustomerReturnIntentionRecorded;
use App\Models\Business;
use App\Notifications\CustomerReturnIntentionNotification;

class NotifyStaffOfCustomerReturnIntention
{
    public function handle(CustomerReturnIntentionRecorded $event): void
    {
        $business = Business::query()->find($event->customer->business_id);
        if ($business === null) {
            return;
        }

        $business->members()
            ->wherePivot('status', 'active')
            ->get()
            ->each(function ($member) use ($business, $event): void {
                if ((int) $member->getKey() === $event->actorId
                    || ! $member->hasBusinessPermission((int) $business->getKey(), 'customers.view')) {
                    return;
                }

                $member->notify(new CustomerReturnIntentionNotification(
                    (int) $event->customer->getKey(),
                    trim($event->customer->first_name.' '.($event->customer->last_name ?? '')),
                    $event->customer->phone,
                    $event->intendedReturnDate,
                ));
            });
    }
}
