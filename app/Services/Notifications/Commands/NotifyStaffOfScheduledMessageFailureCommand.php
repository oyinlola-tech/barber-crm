<?php

namespace App\Services\Notifications\Commands;

use App\Enums\ScheduledMessageStatus;
use App\Jobs\Notifications\SendScheduledMessageFailureAlertJob;
use App\Models\Business;
use App\Models\ScheduledMessage;
use App\Notifications\ScheduledMessageFailedNotification;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

class NotifyStaffOfScheduledMessageFailureCommand
{
    public function execute(int $businessId, int $scheduledMessageId): void
    {
        $message = ScheduledMessage::query()
            ->where('business_id', $businessId)
            ->where('status', ScheduledMessageStatus::Failed->value)
            ->find($scheduledMessageId);
        $business = Business::query()->find($businessId);

        if ($message === null || $message->failed_at === null || $business === null) {
            return;
        }

        $failureTime = Carbon::parse($message->failed_at)->format('Y-m-d\TH:i:s.uP');
        $business->members()->wherePivot('status', 'active')->get()
            ->filter(fn ($member): bool => $member->hasBusinessPermission($businessId, 'messages.view'))
            ->each(function ($member) use ($businessId, $scheduledMessageId, $failureTime): void {
                $notificationId = Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', [
                    'scheduled-message-failed',
                    $businessId,
                    $scheduledMessageId,
                    $member->getKey(),
                    $failureTime,
                ]))->toString();

                if ($member->notifications()->whereKey($notificationId)->exists()) {
                    return;
                }

                $member->notifyNow(new ScheduledMessageFailedNotification($notificationId), ['database']);
                SendScheduledMessageFailureAlertJob::dispatch(
                    (int) $member->getKey(),
                    $businessId,
                    $notificationId,
                )->afterCommit();
            });
    }
}
