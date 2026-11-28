<?php

namespace App\Services\Notifications\Commands;

use App\Interfaces\Repositories\Audit\ActivityDigestRepositoryInterface;
use App\Jobs\Notifications\SendDailyActivityDigestJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DispatchDailyActivityDigestCommand
{
    public function __construct(private readonly ActivityDigestRepositoryInterface $repository) {}

    public function execute(): bool
    {
        $business = $this->repository->activeBusiness();
        if ($business === null) {
            return false;
        }

        $timezone = $business->timezone ?: (string) config('app.timezone');
        $date = CarbonImmutable::now($timezone)->subDay()->toDateString();
        $cacheKey = "activity-digest:dispatched:{$business->getKey()}:{$date}";

        if (! Cache::add($cacheKey, true, now()->addDays(2))) {
            return false;
        }

        try {
            SendDailyActivityDigestJob::dispatch((int) $business->getKey(), $date)->afterCommit();
        } catch (Throwable $exception) {
            Cache::forget($cacheKey);
            throw $exception;
        }

        return true;
    }
}
