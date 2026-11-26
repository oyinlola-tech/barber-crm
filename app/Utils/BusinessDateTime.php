<?php

namespace App\Utils;

use Carbon\CarbonImmutable;

class BusinessDateTime
{
    public static function toUtc(string $dateTime, string $timezone): string
    {
        return CarbonImmutable::parse($dateTime, $timezone)->utc()->format('Y-m-d H:i:s');
    }

    public static function isFuture(string $dateTime, string $timezone): bool
    {
        return CarbonImmutable::parse($dateTime, $timezone)->isAfter(now($timezone));
    }
}
