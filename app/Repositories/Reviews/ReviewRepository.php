<?php

namespace App\Repositories\Reviews;

use App\Interfaces\Repositories\Reviews\ReviewRepositoryInterface;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewRepository implements ReviewRepositoryInterface
{
    public function paginateForBusiness(int $businessId): LengthAwarePaginator
    {
        return Review::query()->where('business_id', $businessId)
            ->with(['customer:id,first_name,last_name', 'visit:id,visited_at'])
            ->orderByDesc('reviewed_at')->orderByDesc('id')->paginate(30);
    }

    public function createForBusiness(int $businessId, array $attributes): Review
    {
        return Review::query()->create([...$attributes, 'business_id' => $businessId]);
    }
}
