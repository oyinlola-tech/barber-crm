<?php

namespace App\Services\Reviews\Commands;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Interfaces\Repositories\Reviews\ReviewRepositoryInterface;
use App\Interfaces\Repositories\Visits\VisitRepositoryInterface;
use App\Models\Review;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Services\Automations\Commands\CancelCustomerFollowUpsCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordReviewCommand
{
    public function __construct(
        public CustomerRepositoryInterface $customers,
        public ReviewRepositoryInterface $reviews,
        public VisitRepositoryInterface $visits,
        public RecordAuditLogCommand $audit,
        public CancelCustomerFollowUpsCommand $cancelCustomerFollowUps,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(int $businessId, int $userId, array $attributes): Review
    {
        return DB::transaction(function () use ($businessId, $userId, $attributes): Review {
            $actor = User::query()->findOrFail($userId);
            abort_unless(
                $actor->hasBusinessPermission($businessId, 'reviews.create')
                    && $actor->hasBusinessPermission($businessId, 'customers.view'),
                403,
            );
            $customer = $this->customers->findForBusiness($businessId, $attributes['customer_id']);
            if (isset($attributes['visit_id'])) {
                $visit = $this->visits->findForBusiness($businessId, $attributes['visit_id']);
                if ((int) $visit->customer_id !== (int) $customer->getKey()) {
                    throw ValidationException::withMessages(['visit_id' => 'The selected visit does not belong to this customer.']);
                }
            }

            $review = $this->reviews->createForBusiness($businessId, [
                ...$attributes,
                'reviewed_at' => $attributes['reviewed_at'] ?? now(),
                'source' => $attributes['source'] ?? 'internal',
            ]);
            $this->cancelCustomerFollowUps->execute(
                $businessId,
                (int) $customer->getKey(),
                onlyTemplatePurpose: 'review_request',
            );
            $this->audit->execute($businessId, $userId, 'review.recorded', Review::class, $review->getKey(), null, ['rating' => $review->rating, 'customer_id' => $customer->getKey()]);

            return $review;
        });
    }
}
