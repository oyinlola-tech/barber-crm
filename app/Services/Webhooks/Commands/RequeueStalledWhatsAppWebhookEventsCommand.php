<?php

namespace App\Services\Webhooks\Commands;

use App\Configs\WhatsAppConfig;
use App\Jobs\WhatsApp\ProcessWhatsAppWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RequeueStalledWhatsAppWebhookEventsCommand
{
    public function __construct(private readonly WhatsAppConfig $config) {}

    public function execute(int $limit = 200): int
    {
        $cutoff = now()->subMinutes($this->config->webhookDispatchRecoveryMinutes());
        $limit = max(1, min($limit, 1000));

        /** @var Collection<int, int> $eventIds */
        $eventIds = DB::transaction(function () use ($cutoff, $limit): Collection {
            $events = WebhookEvent::query()
                ->where('provider', 'whatsapp')
                ->where('status', 'received')
                ->whereNotNull('business_id')
                ->where('updated_at', '<=', $cutoff)
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($events as $event) {
                $event->forceFill(['updated_at' => now()])->saveQuietly();
            }

            return $events->map(static fn (WebhookEvent $event): int => (int) $event->getKey());
        }, 3);

        foreach ($eventIds as $eventId) {
            ProcessWhatsAppWebhookJob::dispatch($eventId);
        }

        return $eventIds->count();
    }
}
