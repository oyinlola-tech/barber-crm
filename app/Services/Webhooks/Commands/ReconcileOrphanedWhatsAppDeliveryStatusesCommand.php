<?php

namespace App\Services\Webhooks\Commands;

use App\Models\MessageEvent;
use App\Models\WebhookEvent;
use App\Services\WhatsApp\Commands\ApplyWhatsAppDeliveryStatusCommand;
use Illuminate\Support\Facades\DB;

class ReconcileOrphanedWhatsAppDeliveryStatusesCommand
{
    /** @return array{checked:int,reconciled:int} */
    public function execute(int $limit = 200): array
    {
        $limit = max(1, min($limit, 1000));
        $eventIds = MessageEvent::query()
            ->whereNull('message_id')
            ->where('event_type', 'message.status')
            ->whereIn('status', ['sent', 'delivered', 'read', 'failed'])
            ->whereNotNull('provider_message_id')
            ->whereHas('webhookEvent', fn ($query) => $query
                ->where('provider', 'whatsapp')
                ->whereIn('status', ['awaiting_message', 'unmatched_message', 'failed']))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $reconciled = 0;
        $applyStatus = app(ApplyWhatsAppDeliveryStatusCommand::class);

        foreach ($eventIds as $eventId) {
            $wasReconciled = DB::transaction(function () use ($eventId, $applyStatus): bool {
                $statusEvent = MessageEvent::query()->find($eventId);
                if ($statusEvent === null || $statusEvent->webhook_event_id === null) {
                    return false;
                }

                $webhook = WebhookEvent::query()
                    ->whereKey($statusEvent->webhook_event_id)
                    ->lockForUpdate()
                    ->first();
                if ($webhook === null
                    || $webhook->business_id !== $statusEvent->business_id
                    || ! in_array($webhook->status, ['awaiting_message', 'unmatched_message', 'failed'], true)) {
                    return false;
                }

                if (! $applyStatus->execute((int) $statusEvent->getKey())) {
                    return false;
                }

                $statusEvent->refresh();
                if ($statusEvent->message_id === null) {
                    return false;
                }

                $webhook->update([
                    'status' => 'processed',
                    'payload' => ['kind' => 'status', 'processed' => true],
                    'processed_at' => now(),
                ]);

                return true;
            }, 3);

            if (! $wasReconciled) {
                continue;
            }

            $reconciled++;
        }

        return ['checked' => $eventIds->count(), 'reconciled' => $reconciled];
    }
}
