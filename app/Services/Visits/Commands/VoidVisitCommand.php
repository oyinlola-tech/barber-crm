<?php

namespace App\Services\Visits\Commands;

use App\Interfaces\Repositories\Visits\VisitRepositoryInterface;
use App\Models\User;
use App\Models\Visit;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Services\Automations\Commands\CancelVisitFollowUpsCommand;
use Illuminate\Support\Facades\DB;

class VoidVisitCommand
{
    public function __construct(
        public VisitRepositoryInterface $visits,
        public CancelVisitFollowUpsCommand $cancelFollowUps,
        public RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $businessId, int $visitId, User $actor): void
    {
        abort_unless($actor->hasBusinessPermission($businessId, 'visits.delete'), 403);

        DB::transaction(function () use ($businessId, $visitId, $actor): void {
            $visit = $this->visits->lockForBusiness($businessId, $visitId);
            $this->cancelFollowUps->execute($businessId, $visitId, 'Cancelled because the completed visit was voided.');
            $this->visits->voidForBusiness($businessId, $visitId);
            $this->audit->execute($businessId, (int) $actor->getKey(), 'visit.voided', Visit::class, $visitId, [
                'customer_id' => $visit->customer_id,
                'visited_at' => $visit->visited_at->toISOString(),
            ], ['voided' => true]);
        });
    }
}
