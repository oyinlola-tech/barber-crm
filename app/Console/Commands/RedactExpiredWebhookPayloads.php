<?php

namespace App\Console\Commands;

use App\Configs\WhatsAppConfig;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RedactExpiredWebhookPayloads extends Command
{
    protected $signature = 'webhooks:redact-expired-payloads';

    protected $description = 'Redact expired WhatsApp webhook payloads while retaining event IDs for deduplication';

    public function handle(WhatsAppConfig $configuration): int
    {
        $cutoff = Carbon::now()->subDays($configuration->webhookPayloadRetentionDays());
        $redacted = 0;

        WebhookEvent::query()
            ->where('provider', 'whatsapp')
            ->whereIn('status', ['processed', 'unmatched_connection', 'unmatched_message', 'failed'])
            ->where('updated_at', '<=', $cutoff)
            ->select(['id', 'payload'])
            ->chunkById(500, function ($events) use (&$redacted): void {
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

        $this->info("Redacted {$redacted} expired WhatsApp webhook payloads.");

        return self::SUCCESS;
    }
}
