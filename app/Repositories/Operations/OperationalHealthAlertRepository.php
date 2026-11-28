<?php

namespace App\Repositories\Operations;

use App\Interfaces\Repositories\Operations\OperationalHealthAlertRepositoryInterface;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Collection;

class OperationalHealthAlertRepository implements OperationalHealthAlertRepositoryInterface
{
    public function activeBusiness(): ?Business
    {
        return Business::query()->where('is_active', true)->orderBy('id')->first();
    }

    /** @return Collection<int, User> */
    public function alertRecipients(int $businessId): Collection
    {
        return Business::query()->findOrFail($businessId)
            ->members()
            ->wherePivot('status', 'active')
            ->get()
            ->filter(static fn (User $user): bool => $user->hasBusinessPermission($businessId, 'system.health.view'))
            ->values();
    }
}
