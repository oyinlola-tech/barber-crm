<?php

namespace App\Policies;

use App\Models\ServiceCategory;
use App\Models\User;

class ServiceCategoryPolicy
{
    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'services.categories.create');
    }

    public function update(User $user, ServiceCategory $category): bool
    {
        return $this->belongsToCurrentBusiness($category)
            && $this->hasPermission($user, 'services.categories.update');
    }

    public function delete(User $user, ServiceCategory $category): bool
    {
        return $this->belongsToCurrentBusiness($category)
            && $this->hasPermission($user, 'services.categories.delete');
    }

    private function belongsToCurrentBusiness(ServiceCategory $category): bool
    {
        return (int) $category->business_id === (int) request()->attributes->get('currentBusiness')?->getKey();
    }

    private function hasPermission(User $user, string $permission): bool
    {
        $businessId = request()->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && $user->hasBusinessPermission((int) $businessId, $permission);
    }
}
