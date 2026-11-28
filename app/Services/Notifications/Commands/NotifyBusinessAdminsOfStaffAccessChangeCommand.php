<?php

namespace App\Services\Notifications\Commands;

use App\Models\Business;
use App\Models\User;
use App\Notifications\StaffAccessChangedNotification;
use Ramsey\Uuid\Uuid;

class NotifyBusinessAdminsOfStaffAccessChangeCommand
{
    public function execute(
        int $businessId,
        int $actorId,
        int $auditLogId,
        string $staffName,
        string $summary,
    ): void {
        $business = Business::query()->find($businessId);
        if ($business === null) {
            return;
        }

        $business->members()->wherePivot('status', 'active')->get()
            ->filter(fn (User $member): bool => (int) $member->getKey() !== $actorId
                && $member->hasBusinessPermission($businessId, 'audit.view'))
            ->each(function ($member) use ($businessId, $auditLogId, $staffName, $summary): void {
                $notificationId = Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', [
                    'staff-access-change',
                    $businessId,
                    $auditLogId,
                    $member->getKey(),
                ]))->toString();

                if ($member->notifications()->whereKey($notificationId)->exists()) {
                    return;
                }

                $member->notify(new StaffAccessChangedNotification(
                    $notificationId,
                    $staffName,
                    $summary,
                    '/operations/audit',
                ));
            });
    }
}
