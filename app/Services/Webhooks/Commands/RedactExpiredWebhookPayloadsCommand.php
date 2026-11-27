<?php

namespace App\Services\Webhooks\Commands;

use App\Configs\WhatsAppConfig;
use App\Models\WebhookEvent;
use Illuminate\Support\Carbon;

class RedactExpiredWebhookPayloadsCommand
{
    public function __construct(private readonly WhatsAppConfig $configuration) {}

    public function execute(int $batchSize = 500): int
    {
        $batchSize = max(1, min($batchSize, 2000));
        $cutoff = Carbon::now()->subDays($this->configuration->webhookPayloadRetentionDays());
        $redacted = 0;

        $redacted += WebhookEvent::query()
            ->where('provider', 'whatsapp')
            ->where('status', 'received')
            ->where('updated_at', '<=', $cutoff)
            ->update([
                'payload' => json_encode(['redacted' => true]),
                'status' => 'failed',
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

        WebhookEvent::query()
            ->where('provider', 'whatsapp')
            ->whereIn('status', ['processed', 'unmatched_connection', 'unmatched_message', 'failed'])
            ->where('updated_at', '<=', $cutoff)
            ->select(['id', 'payload'])
            ->chunkById($batchSize, function ($events) use (&$redacted): void {
                $eventIds = $events
                    ->filter(fn (WebhookEvent $event): bool => $event->payload !== ['redacted' => true])
                    ->modelKeys();
                if ($eventIds === []) {
                    return;
                }

                $redacted += WebhookEvent::query()
                    ->whereIn('id', $eventIds)
                    ->update(['payload' => json_encode(['redacted' => true])]);
            });

        WebhookEvent::query()
            ->where('provider', 'whatsapp')
            ->where('status', 'awaiting_message')
            ->where('updated_at', '<=', $cutoff)
            ->select(['id', 'payload'])
            ->chunkById($batchSize, function ($events) use (&$redacted): void {
                $eventIds = $events
                    ->filter(fn (WebhookEvent $event): bool => $event->payload !== ['redacted' => true])
                    ->modelKeys();
                if ($eventIds === []) {
                    return;
                }

                $redacted += WebhookEvent::query()
                    ->whereIn('id', $eventIds)
                    ->update(['payload' => json_encode(['redacted' => true])]);
            });

        return $redacted;
    }
}
