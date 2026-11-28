<?php

namespace App\Interfaces\Repositories\Audit;

use App\Dtos\Audit\DailyActivityDigestDto;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Collection;

interface ActivityDigestRepositoryInterface
{
    public function activeBusiness(): ?Business;

    public function dailySummary(int $businessId, string $date, string $timezone): DailyActivityDigestDto;

    /** @return Collection<int, User> */
    public function digestRecipients(int $businessId): Collection;
}
