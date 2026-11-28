<?php

namespace App\Services\Notifications\Commands;

use App\Models\WebPushSubscription;

class SaveWebPushSubscriptionCommand
{
    /** @param array{endpoint:string,keys:array{p256dh:string,auth:string}} $subscription */
    public function execute(int $userId, array $subscription): void
    {
        $endpoint = $subscription['endpoint'];
        WebPushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'user_id' => $userId,
                'endpoint' => $endpoint,
                'public_key' => $subscription['keys']['p256dh'],
                'auth_token' => $subscription['keys']['auth'],
            ],
        );
    }
}
