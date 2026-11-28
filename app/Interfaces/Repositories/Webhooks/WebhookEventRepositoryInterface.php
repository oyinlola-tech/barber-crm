<?php

namespace App\Interfaces\Repositories\Webhooks;

use App\Models\WebhookEvent;

interface WebhookEventRepositoryInterface
{
    /** @param array<string, mixed> $payload */
    public function storeIfAbsent(
        ?int $businessId,
        string $eventId,
        string $eventType,
        array $payload,
        string $status,
    ): ?WebhookEvent;
}
