<?php

namespace App\Interfaces\Repositories\Reviews;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReviewRepositoryInterface
{
    public function paginateForBusiness(int $businessId): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes */
    public function createForBusiness(int $businessId, array $attributes): Review;
}
