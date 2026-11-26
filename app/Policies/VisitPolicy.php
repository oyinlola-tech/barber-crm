<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;

class VisitPolicy
{
    public function create(User $user): bool
    {
        $businessId = request()->attributes->get('currentBusiness')?->getKey();
        if ($businessId === null) {
            return false;
        }

        return $user->hasBusinessPermission((int) $businessId, 'visits.create');
    }

    public function view(User $user, Visit $visit): bool
    {
        return (int) $visit->business_id === (int) request()->attributes->get('currentBusiness')?->getKey()
            && $user->businesses()->whereKey($visit->business_id)->exists();
    }

    public function update(User $user, Visit $visit): bool
    {
        return (int) $visit->business_id === (int) request()->attributes->get('currentBusiness')?->getKey()
            && ! $visit->trashed()
            && $user->hasBusinessPermission((int) $visit->business_id, 'visits.update');
    }

    public function delete(User $user, Visit $visit): bool
    {
        return (int) $visit->business_id === (int) request()->attributes->get('currentBusiness')?->getKey()
            && ! $visit->trashed()
            && $user->hasBusinessPermission((int) $visit->business_id, 'visits.delete');
    }

    public function restore(User $user, Visit $visit): bool
    {
        return (int) $visit->business_id === (int) request()->attributes->get('currentBusiness')?->getKey()
            && $visit->trashed()
            && $user->hasBusinessPermission((int) $visit->business_id, 'visits.restore');
    }
}
