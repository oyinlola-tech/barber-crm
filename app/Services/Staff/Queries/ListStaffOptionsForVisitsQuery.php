<?php

namespace App\Services\Staff\Queries;

use App\Interfaces\Repositories\Staff\StaffRepositoryInterface;
use App\Models\StaffProfile;

class ListStaffOptionsForVisitsQuery
{
    public function __construct(private readonly StaffRepositoryInterface $staff) {}

    /** @return list<array{id:int,name:string}> */
    public function execute(int $businessId): array
    {
        return $this->staff->listForBusiness($businessId)
            ->map(fn (StaffProfile $profile): array => [
                'id' => (int) $profile->getKey(),
                'name' => $profile->name,
            ])
            ->values()
            ->all();
    }
}
