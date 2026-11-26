<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->belongsToCurrentBusiness($user, $customer->business_id)
            && $user->hasBusinessPermission((int) $customer->business_id, 'customers.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $customer->status === 'active'
            && $this->belongsToCurrentBusiness($user, $customer->business_id)
            && $user->hasBusinessPermission((int) $customer->business_id, 'customers.update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $customer->status === 'active'
            && $this->belongsToCurrentBusiness($user, $customer->business_id)
            && $user->hasBusinessPermission((int) $customer->business_id, 'customers.archive');
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $customer->status === 'archived'
            && $this->belongsToCurrentBusiness($user, $customer->business_id)
            && $user->hasBusinessPermission((int) $customer->business_id, 'customers.restore');
    }

    public function anonymize(User $user, Customer $customer): bool
    {
        return $customer->status !== 'anonymized'
            && $this->belongsToCurrentBusiness($user, $customer->business_id)
            && $user->hasBusinessPermission((int) $customer->business_id, 'customers.anonymize');
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return $this->belongsToCurrentBusiness($user, $customer->business_id)
            && $user->hasBusinessPermission((int) $customer->business_id, 'customers.delete');
    }

    public function addNote(User $user, Customer $customer): bool
    {
        return $customer->status === 'active'
            && $this->belongsToCurrentBusiness($user, $customer->business_id)
            && $user->hasBusinessPermission((int) $customer->business_id, 'customers.note');
    }

    public function manageTags(User $user, Customer $customer): bool
    {
        return $customer->status === 'active'
            && $this->belongsToCurrentBusiness($user, $customer->business_id)
            && $user->hasBusinessPermission((int) $customer->business_id, 'customers.tags.manage');
    }

    private function belongsToCurrentBusiness(User $user, int $businessId): bool
    {
        return $user->businesses()->whereKey($businessId)->exists();
    }

    private function hasPermission(User $user, string $permission): bool
    {
        $businessId = request()->attributes->get('currentBusiness')?->getKey();
        if ($businessId === null) {
            return false;
        }

        return $user->hasBusinessPermission((int) $businessId, $permission);
    }
}
