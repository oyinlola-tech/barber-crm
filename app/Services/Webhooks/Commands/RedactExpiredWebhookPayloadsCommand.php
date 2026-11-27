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

        WebhookEvent::query()
            ->where('provider', 'whatsapp')
            ->whereIn('status', ['processed', 'unmatched_connection', 'unmatched_message', 'failed', 'awaiting_message'])
            ->where('updated_at', '<=', $cutoff)
            ->whereJsonDoesntContain('payload', ['redacted' => true])
            ->select(['id'])
            ->chunkById($batchSize, function ($events) use (&$redacted): void {
                $redacted += WebhookEvent::query()
                    ->whereIn('id', $events->modelKeys())
                    ->update(['payload' => json_encode(['redacted' => true])]);
            });

        return $redacted;
    }
}
