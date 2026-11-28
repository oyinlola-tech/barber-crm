<?php

namespace App\Services\Webhooks\Commands;

use App\Models\WebhookEvent;
use App\Services\Notifications\Commands\NotifyStaffOfWhatsAppWebhookFailureCommand;
use Illuminate\Support\Facades\DB;

class MarkWhatsAppWebhookFailedCommand
{
    public function __construct(private readonly NotifyStaffOfWhatsAppWebhookFailureCommand $notifyStaff) {}

    public function execute(int $webhookEventId): void
    {
        $businessId = DB::transaction(function () use ($webhookEventId): ?int {
            $event = WebhookEvent::query()->lockForUpdate()->find($webhookEventId);
            if ($event === null || $event->status === 'failed') {
                return null;
            }

            $event->update([
                'status' => 'failed',
                'payload' => ['redacted' => true],
                'processed_at' => now(),
            ]);

            return $event->business_id === null ? null : (int) $event->business_id;
        }, 3);

        if ($businessId !== null) {
            $this->notifyStaff->execute($businessId, $webhookEventId);
        }
    }
}
