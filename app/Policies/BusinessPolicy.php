<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function viewSystemHealth(User $user, Business $business): bool
    {
        return $user->hasBusinessPermission((int) $business->getKey(), 'system.health.view');
    }
}
