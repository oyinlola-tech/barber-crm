<?php

namespace App\Services\Consent\Queries;

use App\Models\CustomerConsent;

class CustomerCanReceiveMessageQuery
{
    public function execute(int $businessId, int $customerId, string $channel): bool
    {
        $latestConsent = CustomerConsent::query()
            ->where('business_id', $businessId)
            ->where('customer_id', $customerId)
            ->where('channel', $channel)
            ->latest('recorded_at')
            ->latest('id')
            ->first();

        return $latestConsent?->granted === true && $latestConsent->revoked_at === null;
    }
}
