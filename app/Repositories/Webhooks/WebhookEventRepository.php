<?php

namespace App\Repositories\Webhooks;

use App\Interfaces\Repositories\Webhooks\WebhookEventRepositoryInterface;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use JsonException;

class WebhookEventRepository implements WebhookEventRepositoryInterface
{
    /** @param array<string, mixed> $payload
     * @throws JsonException
     */
    public function storeIfAbsent(
        ?int $businessId,
        string $eventId,
        string $eventType,
        array $payload,
        string $status,
    ): ?WebhookEvent {
        $now = now();
        $inserted = DB::table('webhook_events')->insertOrIgnore([
            'business_id' => $businessId,
            'provider' => 'whatsapp',
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted !== 1) {
            return null;
        }

        return WebhookEvent::query()
            ->where('provider', 'whatsapp')
            ->where('event_id', $eventId)
            ->firstOrFail();
    }
}
