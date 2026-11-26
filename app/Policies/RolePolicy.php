<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user, Business $business): bool
    {
        return $user->hasBusinessPermission((int) $business->getKey(), 'roles.view');
    }

    public function create(User $user, Business $business): bool
    {
        return $user->hasBusinessPermission((int) $business->getKey(), 'roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $role->business_id !== null
            && $user->hasBusinessPermission((int) $role->business_id, 'roles.update')
            && $this->canDelegate($user, (int) $role->business_id, $role->permissions ?? []);
    }

    public function delete(User $user, Role $role): bool
    {
        return $role->business_id !== null && $user->hasBusinessPermission((int) $role->business_id, 'roles.delete');
    }

    /** @param list<string> $permissions */
    private function canDelegate(User $user, int $businessId, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! isset(BusinessRole::permissions()[$permission]) || ! $user->hasBusinessPermission($businessId, $permission)) {
                return false;
            }
        }

        return true;
    }
}
