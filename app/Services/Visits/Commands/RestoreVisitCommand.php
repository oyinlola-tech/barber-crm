<?php

namespace App\Services\Visits\Commands;

use App\Events\Visits\VisitRestored;
use App\Interfaces\Repositories\Visits\VisitRepositoryInterface;
use App\Models\User;
use App\Models\Visit;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class RestoreVisitCommand
{
    public function __construct(
        public VisitRepositoryInterface $visits,
        public RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $businessId, int $visitId, User $actor): Visit
    {
        abort_unless($actor->hasBusinessPermission($businessId, 'visits.restore'), 403);

        return DB::transaction(function () use ($businessId, $visitId, $actor): Visit {
            $visit = $this->visits->restoreForBusiness($businessId, $visitId);
            $this->audit->execute($businessId, (int) $actor->getKey(), 'visit.restored', Visit::class, $visitId, ['voided' => true], ['voided' => false]);
            VisitRestored::dispatch($visit);

            return $visit;
        });
    }
}
