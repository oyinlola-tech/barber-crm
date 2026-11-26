<?php

namespace App\Services\Visits\Commands;

use App\Interfaces\Repositories\Visits\VisitRepositoryInterface;
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

    public function execute(int $businessId, int $visitId, int $userId): void
    {
        DB::transaction(function () use ($businessId, $visitId, $userId): void {
            $visit = $this->visits->lockForBusiness($businessId, $visitId);
            $this->cancelFollowUps->execute($businessId, $visitId, 'Cancelled because the completed visit was voided.');
            $this->visits->voidForBusiness($businessId, $visitId);
            $this->audit->execute($businessId, $userId, 'visit.voided', Visit::class, $visitId, [
                'customer_id' => $visit->customer_id,
                'visited_at' => $visit->visited_at->toISOString(),
            ], ['voided' => true]);
        });
    }
}
