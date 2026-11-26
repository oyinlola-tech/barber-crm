<?php

namespace App\Services\Visits\Commands;

use App\Events\Visits\VisitCorrected;
use App\Interfaces\Repositories\Services\ServiceRepositoryInterface;
use App\Interfaces\Repositories\Staff\StaffRepositoryInterface;
use App\Interfaces\Repositories\Visits\VisitRepositoryInterface;
use App\Models\Business;
use App\Models\User;
use App\Models\Visit;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Services\Automations\Commands\CancelVisitFollowUpsCommand;
use App\Utils\BusinessDateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateVisitCommand
{
    public function __construct(
        public ServiceRepositoryInterface $services,
        public StaffRepositoryInterface $staff,
        public VisitRepositoryInterface $visits,
        public CancelVisitFollowUpsCommand $cancelFollowUps,
        public RecordAuditLogCommand $audit,
    ) {}

    /** @param array{staff_profile_id?:int|null,visited_at:string,notes?:string|null,service_ids?:list<int>} $attributes */
    public function execute(int $businessId, int $visitId, User $actor, array $attributes): Visit
    {
        abort_unless($actor->hasBusinessPermission($businessId, 'visits.update'), 403);

        return DB::transaction(function () use ($businessId, $visitId, $actor, $attributes): Visit {
            $visit = $this->visits->lockForBusiness($businessId, $visitId);
            if ($visit->customer->status !== 'active' || $visit->customer->archived_at !== null) {
                throw ValidationException::withMessages(['visit' => 'Visits for archived or anonymized customers cannot be edited.']);
            }
            if (! empty($attributes['staff_profile_id'])) {
                $this->staff->findForBusiness($businessId, $attributes['staff_profile_id']);
            }

            $serviceIds = $attributes['service_ids'] ?? [];
            $services = $this->services->findManyForBusiness($businessId, $serviceIds);
            if ($services->count() !== count(array_unique($serviceIds))) {
                throw ValidationException::withMessages(['service_ids' => 'One or more services are unavailable.']);
            }

            $before = [
                'visited_at' => $visit->visited_at->toISOString(),
                'staff_profile_id' => $visit->staff_profile_id,
                'service_ids' => $visit->services()->pluck('service_id')->all(),
            ];
            $this->cancelFollowUps->execute($businessId, $visitId, 'Cancelled because the completed visit was corrected.');
            $updatedVisit = $this->visits->updateForBusiness($businessId, $visitId, [
                'staff_profile_id' => $attributes['staff_profile_id'] ?? null,
                'visited_at' => BusinessDateTime::toUtc($attributes['visited_at'], Business::query()->findOrFail($businessId)->timezone),
                'notes' => $attributes['notes'] ?? null,
            ], $services);
            $this->audit->execute(
                $businessId,
                (int) $actor->getKey(),
                'visit.updated',
                Visit::class,
                $visitId,
                $before,
                [
                    'visited_at' => $updatedVisit->visited_at->toISOString(),
                    'staff_profile_id' => $updatedVisit->staff_profile_id,
                    'service_ids' => $updatedVisit->services->pluck('service_id')->all(),
                ],
            );
            VisitCorrected::dispatch($updatedVisit);

            return $updatedVisit;
        });
    }
}
