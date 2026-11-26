<?php

namespace App\Services\Automations;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

class SendWindowCalculator
{
    /** @param array{start?: string, end?: string}|null $sendingHours */
    public function nextAllowedAt(DateTimeInterface $scheduledAt, string $timezone, ?array $sendingHours = null): CarbonImmutable
    {
        try {
            $businessTimezone = new DateTimeZone($timezone);
        } catch (Exception) {
            $businessTimezone = new DateTimeZone('UTC');
        }

        $window = $sendingHours ?? config('automation.default_sending_hours');
        $start = (string) ($window['start'] ?? '09:00');
        $end = (string) ($window['end'] ?? '18:00');
        $localScheduledAt = CarbonImmutable::instance($scheduledAt)->setTimezone($businessTimezone);
        $startAt = $localScheduledAt->setTimeFromTimeString($start);
        $endAt = $localScheduledAt->setTimeFromTimeString($end);

        if ($localScheduledAt->greaterThanOrEqualTo($startAt) && $localScheduledAt->lessThan($endAt)) {
            return $localScheduledAt->utc();
        }

        if ($localScheduledAt->lessThan($startAt)) {
            return $startAt->utc();
        }

        return $startAt->addDay()->utc();
    }
}
