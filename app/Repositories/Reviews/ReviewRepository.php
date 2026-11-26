<?php

namespace App\Repositories\Reviews;

use App\Interfaces\Repositories\Reviews\ReviewRepositoryInterface;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

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

    public function updateForBusiness(int $businessId, int $reviewId, array $attributes): Review
    {
        $review = Review::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($reviewId);
        $review->update($attributes);

        return $review->refresh();
    }

    public function findForBusinessForUpdate(int $businessId, int $reviewId): Review
    {
        return Review::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($reviewId);
    }

    /** @return LazyCollection<int, array{id:int,rating:int,body:string,source:string,reviewed_at:string,customer_name:string,customer_phone:string,visit_at:string}> */
    public function cursorForBusinessExport(int $businessId): LazyCollection
    {
        return DB::table('reviews')
            ->join('customers', 'customers.id', '=', 'reviews.customer_id')
            ->leftJoin('visits', 'visits.id', '=', 'reviews.visit_id')
            ->where('reviews.business_id', $businessId)
            ->orderBy('reviews.id')
            ->select([
                'reviews.id', 'reviews.rating', 'reviews.body', 'reviews.source', 'reviews.reviewed_at',
                'customers.first_name', 'customers.last_name', 'customers.phone', 'visits.visited_at',
            ])
            ->cursor()
            ->map(static fn (object $review): array => [
                'id' => (int) $review->id,
                'rating' => (int) $review->rating,
                'body' => $review->body === null ? '' : (string) $review->body,
                'source' => (string) $review->source,
                'reviewed_at' => (string) $review->reviewed_at,
                'customer_name' => trim((string) $review->first_name.' '.(string) $review->last_name),
                'customer_phone' => (string) $review->phone,
                'visit_at' => $review->visited_at === null ? '' : (string) $review->visited_at,
            ]);
    }
}
