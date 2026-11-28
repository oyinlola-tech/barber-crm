<?php

namespace App\Services\Audit\Queries;

use App\Dtos\Audit\DailyActivityDigestDto;
use App\Interfaces\Repositories\Audit\ActivityDigestRepositoryInterface;

class GetDailyActivityDigestQuery
{
    public function __construct(private readonly ActivityDigestRepositoryInterface $repository) {}

    public function execute(int $businessId, string $date, string $timezone): DailyActivityDigestDto
    {
        return $this->repository->dailySummary($businessId, $date, $timezone);
    }
}
