<?php

namespace App\Services\Notifications\Commands;

use App\Jobs\Notifications\SendWhatsAppWebhookFailureAlertJob;
use App\Models\Business;
use App\Models\WebhookEvent;
use App\Notifications\WhatsAppWebhookFailedNotification;
use Ramsey\Uuid\Uuid;

class NotifyStaffOfWhatsAppWebhookFailureCommand
{
    public function execute(int $businessId, int $webhookEventId): void
    {
        $business = Business::query()->find($businessId);
        $event = WebhookEvent::query()
            ->where('business_id', $businessId)
            ->where('status', 'failed')
            ->find($webhookEventId);

        if ($business === null || $event === null) {
            return;
        }

        $business->members()->wherePivot('status', 'active')->get()
            ->filter(fn ($member): bool => $member->hasBusinessPermission($businessId, 'system.health.view'))
            ->each(function ($member) use ($businessId, $webhookEventId): void {
                $notificationId = Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', [
                    'whatsapp-webhook-failed',
                    $businessId,
                    $webhookEventId,
                    $member->getKey(),
                ]))->toString();

                if ($member->notifications()->whereKey($notificationId)->exists()) {
                    return;
                }

                $member->notifyNow(new WhatsAppWebhookFailedNotification($notificationId), ['database']);
                SendWhatsAppWebhookFailureAlertJob::dispatch(
                    (int) $member->getKey(),
                    $businessId,
                    $notificationId,
                )->afterCommit();
            });
    }
}
