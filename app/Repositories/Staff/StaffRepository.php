<?php

namespace App\Repositories\Staff;

use App\Interfaces\Repositories\Staff\StaffRepositoryInterface;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Collection;

class StaffRepository implements StaffRepositoryInterface
{
    public function listForBusiness(int $businessId): Collection
    {
        return StaffProfile::query()->where('business_id', $businessId)->with('user:id,name,email')->orderBy('name')->get();
    }

    public function findForBusiness(int $businessId, int $staffId): StaffProfile
    {
        return StaffProfile::query()->where('business_id', $businessId)->findOrFail($staffId);
    }

    public function lockForBusiness(int $businessId, int $staffId): StaffProfile
    {
        return StaffProfile::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($staffId);
    }
}
