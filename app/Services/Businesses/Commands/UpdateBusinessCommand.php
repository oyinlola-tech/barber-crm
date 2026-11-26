<?php

namespace App\Services\Businesses\Commands;

use App\Models\Business;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class UpdateBusinessCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    /** @param array{name:string,phone?:string|null,email?:string|null,timezone:string,currency:string} $attributes */
    public function execute(int $businessId, int $userId, array $attributes): Business
    {
        return DB::transaction(function () use ($businessId, $userId, $attributes): Business {
            $business = Business::query()->lockForUpdate()->findOrFail($businessId);
            $before = $business->only(['name', 'phone', 'email', 'timezone', 'currency']);
            $business->update($attributes);
            $this->audit->execute($businessId, $userId, 'business.settings_updated', Business::class, $business->getKey(), $before, $business->only(array_keys($before)));

            return $business->refresh();
        });
    }
}
