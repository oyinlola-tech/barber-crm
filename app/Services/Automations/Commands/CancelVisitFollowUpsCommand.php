<?php

namespace App\Services\Automations\Commands;

use App\Models\ScheduledMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CancelVisitFollowUpsCommand
{
    public function execute(int $businessId, int $visitId, string $reason): int
    {
        return DB::transaction(fn (): int => ScheduledMessage::query()
            ->where('business_id', $businessId)
            ->whereIn('status', ['pending', 'queued'])
            ->where(function (Builder $query) use ($visitId): void {
                $query->whereJsonContains('payload->visit_id', $visitId);
            })
            ->update([
                'status' => 'cancelled',
                'last_error' => $reason,
                'claimed_at' => null,
                'updated_at' => now(),
            ]));
    }
}
