<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'services.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'services.create');
    }

    public function update(User $user, Service $service): bool
    {
        return (int) $service->business_id === (int) request()->attributes->get('currentBusiness')?->getKey()
            && $this->hasPermission($user, 'services.update');
    }

    private function hasPermission(User $user, string $permission): bool
    {
        $businessId = request()->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && $user->hasBusinessPermission((int) $businessId, $permission);
    }
}
