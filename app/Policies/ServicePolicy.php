<?php

namespace App\Policies;

use App\Enums\BusinessRole;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasRole($user, BusinessRole::Owner, BusinessRole::Manager, BusinessRole::Barber, BusinessRole::Receptionist);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, BusinessRole::Owner, BusinessRole::Manager);
    }

    private function hasRole(User $user, BusinessRole ...$roles): bool
    {
        $businessId = request()->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && $user->businesses()->whereKey($businessId)
            ->wherePivotIn('role', array_map(fn (BusinessRole $role): string => $role->value, $roles))->exists();
    }
}
