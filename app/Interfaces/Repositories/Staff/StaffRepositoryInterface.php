<?php

namespace App\Interfaces\Repositories\Staff;

use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Collection;

interface StaffRepositoryInterface
{
    /** @return Collection<int, StaffProfile> */
    public function listForBusiness(int $businessId): Collection;

    public function findForBusiness(int $businessId, int $staffId): StaffProfile;

    public function lockForBusiness(int $businessId, int $staffId): StaffProfile;
}
