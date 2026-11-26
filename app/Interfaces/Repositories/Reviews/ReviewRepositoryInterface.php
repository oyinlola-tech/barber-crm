<?php

namespace App\Interfaces\Repositories\Reviews;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

interface ReviewRepositoryInterface
{
    public function paginateForBusiness(int $businessId): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes */
    public function createForBusiness(int $businessId, array $attributes): Review;

    /** @param array<string, mixed> $attributes */
    public function updateForBusiness(int $businessId, int $reviewId, array $attributes): Review;

    public function findForBusinessForUpdate(int $businessId, int $reviewId): Review;

    /** @return LazyCollection<int, array{id:int,rating:int,body:string,source:string,reviewed_at:string,customer_name:string,customer_phone:string,visit_at:string}> */
    public function cursorForBusinessExport(int $businessId): LazyCollection;
}
