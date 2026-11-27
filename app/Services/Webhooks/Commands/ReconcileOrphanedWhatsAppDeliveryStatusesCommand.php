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
            $reconciled += DB::transaction(function () use ($eventId, $applyStatus): int {
                $firstEvent = MessageEvent::query()->find($eventId);
                if ($firstEvent === null || $firstEvent->message_id !== null || $firstEvent->webhook_event_id === null) {
                    return 0;
                }

                $orphanEvents = MessageEvent::query()
                    ->whereNull('message_id')
                    ->where('business_id', $firstEvent->business_id)
                    ->where('provider_message_id', $firstEvent->provider_message_id)
                    ->where('event_type', 'message.status')
                    ->whereIn('status', ['sent', 'delivered', 'read', 'failed'])
                    ->orderBy('occurred_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                if ($orphanEvents->isEmpty()) {
                    return 0;
                }

                $webhookIds = $orphanEvents->pluck('webhook_event_id')->filter()->unique()->sort()->values();
                $webhooks = WebhookEvent::query()
                    ->whereIn('id', $webhookIds)
                    ->where('business_id', $firstEvent->business_id)
                    ->where('provider', 'whatsapp')
                    ->whereIn('status', ['awaiting_message', 'unmatched_message', 'failed'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(fn (WebhookEvent $webhook): int => (int) $webhook->getKey());
                if ($webhooks->isEmpty()) {
                    return 0;
                }

                $processedWebhookIds = [];
                foreach ($orphanEvents as $orphanEvent) {
                    $webhookId = (int) $orphanEvent->webhook_event_id;
                    if (! $webhooks->has($webhookId)) {
                        continue;
                    }

                    if (! $applyStatus->execute((int) $orphanEvent->getKey())) {
                        continue;
                    }

                    if ($orphanEvent->fresh()?->message_id !== null) {
                        $processedWebhookIds[$webhookId] = true;
                    }
                }

                foreach (array_keys($processedWebhookIds) as $processedWebhookId) {
                    $webhooks->get($processedWebhookId)?->update([
                        'status' => 'processed',
                        'payload' => ['kind' => 'status', 'processed' => true],
                        'processed_at' => now(),
                    ]);
                }

                return count($processedWebhookIds);
            }, 3);
        }

        return ['checked' => $eventIds->count(), 'reconciled' => $reconciled];
    }
}
