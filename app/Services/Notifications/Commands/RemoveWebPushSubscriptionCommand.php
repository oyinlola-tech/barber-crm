<?php

namespace App\Services\Notifications\Commands;

use App\Models\WebPushSubscription;

class RemoveWebPushSubscriptionCommand
{
    public function execute(int $userId, string $endpoint): bool
    {
        return (bool) WebPushSubscription::query()
            ->where('user_id', $userId)
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->delete();
    }
}
