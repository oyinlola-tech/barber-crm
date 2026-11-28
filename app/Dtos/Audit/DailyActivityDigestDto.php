<?php

namespace App\Dtos\Audit;

final readonly class DailyActivityDigestDto
{
    /**
     * @param  array<string, int>  $actionCounts
     */
    public function __construct(
        public int $businessId,
        public string $businessName,
        public string $date,
        public array $actionCounts,
        public int $totalEvents,
    ) {}
}
