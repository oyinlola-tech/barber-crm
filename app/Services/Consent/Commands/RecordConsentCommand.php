<?php

namespace App\Services\Consent\Commands;

use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Services\Automations\Commands\CancelCustomerFollowUpsCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordConsentCommand
{
    public function __construct(
        public RecordAuditLogCommand $audit,
        public CancelCustomerFollowUpsCommand $cancelCustomerFollowUps,
    ) {}

    public function execute(int $businessId, int $customerId, string $channel, bool $granted, ?string $source = null, ?int $userId = null): CustomerConsent
    {
        return DB::transaction(function () use ($businessId, $customerId, $channel, $granted, $source, $userId): CustomerConsent {
            $customer = Customer::query()->where('business_id', $businessId)->findOrFail($customerId);
            if ($customer->status !== 'active') {
                throw ValidationException::withMessages(['customer' => 'Consent cannot be changed for an archived or anonymized customer.']);
            }
            CustomerConsent::query()
                ->where('business_id', $businessId)
                ->where('customer_id', $customerId)
                ->where('channel', $channel)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            if (! $granted) {
                $this->cancelCustomerFollowUps->execute($businessId, $customerId);
            }

            $consent = CustomerConsent::query()->create([
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'channel' => $channel,
                'granted' => $granted,
                'source' => $source,
                'recorded_at' => now(),
                'revoked_at' => $granted ? null : now(),
            ]);
            $this->audit->execute($businessId, $userId, $granted ? 'customer.consent_granted' : 'customer.consent_revoked', Customer::class, $customerId, null, ['channel' => $channel, 'granted' => $granted]);

            return $consent;
        });
    }
}
