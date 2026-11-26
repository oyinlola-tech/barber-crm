<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user, Business $business): bool
    {
        return $user->hasBusinessPermission((int) $business->getKey(), 'audit.view');
    }
}
