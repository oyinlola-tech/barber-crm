<?php

namespace App\Repositories\Audit;

use App\Dtos\Audit\DailyActivityDigestDto;
use App\Interfaces\Repositories\Audit\ActivityDigestRepositoryInterface;
use App\Models\Business;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActivityDigestRepository implements ActivityDigestRepositoryInterface
{
    public function activeBusiness(): ?Business
    {
        return Business::query()->where('is_active', true)->orderBy('id')->first();
    }

    public function dailySummary(int $businessId, string $date, string $timezone): DailyActivityDigestDto
    {
        $business = Business::query()->findOrFail($businessId);
        $start = CarbonImmutable::parse($date, $timezone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($date, $timezone)->endOfDay()->utc();

        $actionCounts = DB::table('audit_logs')
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$start, $end])
            ->select('action', DB::raw('COUNT(*) AS event_count'))
            ->groupBy('action')
            ->orderBy('action')
            ->pluck('event_count', 'action')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        return new DailyActivityDigestDto(
            businessId: $businessId,
            businessName: $business->name,
            date: $date,
            actionCounts: $actionCounts,
            totalEvents: array_sum($actionCounts),
        );
    }

    public function digestRecipients(int $businessId): Collection
    {
        return Business::query()->findOrFail($businessId)
            ->members()
            ->wherePivot('status', 'active')
            ->get()
            ->filter(static fn (User $user): bool => $user->hasBusinessPermission($businessId, 'audit.view'))
            ->values();
    }
}
