<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\StaffProfile;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user, Business $business): bool
    {
        return $user->hasBusinessPermission((int) $business->getKey(), 'staff.view');
    }

    public function invite(User $user, Business $business): bool
    {
        return $user->hasBusinessPermission((int) $business->getKey(), 'staff.invite');
    }

    public function view(User $user, StaffProfile $staffProfile): bool
    {
        return $user->hasBusinessPermission((int) $staffProfile->business_id, 'staff.view');
    }

    public function update(User $user, StaffProfile $staffProfile): bool
    {
        return $user->hasBusinessPermission((int) $staffProfile->business_id, 'staff.update')
            && $staffProfile->user_id !== null
            && $this->isNotOwner((int) $staffProfile->business_id, (int) $staffProfile->user_id);
    }

    public function suspend(User $user, StaffProfile $staffProfile): bool
    {
        return $user->hasBusinessPermission((int) $staffProfile->business_id, 'staff.suspend')
            && $staffProfile->user_id !== null
            && (int) $staffProfile->user_id !== (int) $user->getKey()
            && $this->isNotOwner((int) $staffProfile->business_id, (int) $staffProfile->user_id);
    }

    public function activate(User $user, StaffProfile $staffProfile): bool
    {
        return $user->hasBusinessPermission((int) $staffProfile->business_id, 'staff.activate')
            && $staffProfile->user_id !== null
            && (int) $staffProfile->user_id !== (int) $user->getKey()
            && $this->isNotOwner((int) $staffProfile->business_id, (int) $staffProfile->user_id);
    }

    public function delete(User $user, StaffProfile $staffProfile): bool
    {
        return $user->hasBusinessPermission((int) $staffProfile->business_id, 'staff.delete')
            && $staffProfile->user_id !== null
            && $staffProfile->status !== 'deleted'
            && (int) $staffProfile->user_id !== (int) $user->getKey()
            && $this->isNotOwner((int) $staffProfile->business_id, (int) $staffProfile->user_id);
    }

    private function isNotOwner(int $businessId, int $userId): bool
    {
        return ! BusinessMember::query()->where('business_id', $businessId)
            ->where('user_id', $userId)->where('role', 'owner')->exists();
    }
}
