<?php

namespace App\Services\Visits\Commands;

use App\Interfaces\Repositories\Visits\VisitRepositoryInterface;
use App\Models\Visit;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class RestoreVisitCommand
{
    public function __construct(
        public VisitRepositoryInterface $visits,
        public RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $businessId, int $visitId, int $userId): Visit
    {
        return DB::transaction(function () use ($businessId, $visitId, $userId): Visit {
            $visit = $this->visits->restoreForBusiness($businessId, $visitId);
            $this->audit->execute($businessId, $userId, 'visit.restored', Visit::class, $visitId, ['voided' => true], ['voided' => false]);

            return $visit;
        });
    }
}
