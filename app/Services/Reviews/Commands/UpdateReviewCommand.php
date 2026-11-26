<?php

namespace App\Services\Reviews\Commands;

use App\Interfaces\Repositories\Reviews\ReviewRepositoryInterface;
use App\Models\Review;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class UpdateReviewCommand
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
        private readonly RecordAuditLogCommand $audit,
    ) {}

    /** @param array{rating:int, body?:string|null} $attributes */
    public function execute(int $businessId, int $reviewId, int $actorId, array $attributes): Review
    {
        return DB::transaction(function () use ($businessId, $reviewId, $actorId, $attributes): Review {
            $actor = User::query()->findOrFail($actorId);
            abort_unless($actor->hasBusinessPermission($businessId, 'reviews.update'), 403);

            $before = $this->reviews->findForBusinessForUpdate($businessId, $reviewId);
            $review = $this->reviews->updateForBusiness($businessId, $reviewId, $attributes);
            $this->audit->execute($businessId, $actorId, 'review.updated', Review::class, $reviewId,
                ['rating' => $before->rating], ['rating' => $review->rating, 'body_changed' => array_key_exists('body', $attributes)]);

            return $review;
        });
    }
}
