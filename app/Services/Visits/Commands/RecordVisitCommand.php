<?php

namespace App\Services\Visits\Commands;

use App\Events\Visits\VisitCompleted;
use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Interfaces\Repositories\Services\ServiceRepositoryInterface;
use App\Interfaces\Repositories\Staff\StaffRepositoryInterface;
use App\Interfaces\Repositories\Visits\VisitRepositoryInterface;
use App\Models\Business;
use App\Models\User;
use App\Models\Visit;
use App\Utils\BusinessDateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordVisitCommand
{
    public function __construct(
        public CustomerRepositoryInterface $customers,
        public ServiceRepositoryInterface $services,
        public StaffRepositoryInterface $staff,
        public VisitRepositoryInterface $visits,
    ) {}

    /** @param array{customer_id:int,staff_profile_id?:int|null,visited_at:string,notes?:string|null,service_ids?:list<int>} $attributes */
    public function execute(int $businessId, User $actor, array $attributes): Visit
    {
        abort_unless($actor->hasBusinessPermission($businessId, 'visits.create'), 403);

        return DB::transaction(function () use ($businessId, $actor, $attributes): Visit {
            $customer = $this->customers->findForBusiness($businessId, $attributes['customer_id']);
            if ($customer->status !== 'active') {
                throw ValidationException::withMessages(['customer_id' => 'A visit cannot be recorded for an archived or anonymized customer.']);
            }
            if (! empty($attributes['staff_profile_id'])) {
                $this->staff->findForBusiness($businessId, $attributes['staff_profile_id']);
            }
            $serviceIds = $attributes['service_ids'] ?? [];
            $services = $this->services->findManyForBusiness($businessId, $serviceIds);
            if ($services->count() !== count(array_unique($serviceIds))) {
                throw ValidationException::withMessages(['service_ids' => 'One or more services are unavailable.']);
            }

            $visit = $this->visits->createForBusiness($businessId, (int) $actor->getKey(), [
                'customer_id' => $attributes['customer_id'],
                'staff_profile_id' => $attributes['staff_profile_id'] ?? null,
                'visited_at' => BusinessDateTime::toUtc($attributes['visited_at'], Business::query()->findOrFail($businessId)->timezone),
                'notes' => $attributes['notes'] ?? null,
            ], $services);
            VisitCompleted::dispatch($visit);

            return $visit;
        });
    }
}
