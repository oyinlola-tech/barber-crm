<?php

namespace App\Services\Reviews\Queries;

use App\Interfaces\Repositories\Reviews\ReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListReviewsQuery
{
    public function __construct(public ReviewRepositoryInterface $reviews) {}

    public function execute(int $businessId): LengthAwarePaginator
    {
        return $this->reviews->paginateForBusiness($businessId);
    }
}
