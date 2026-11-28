<?php

namespace App\Interfaces\Repositories\Operations;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Collection;

interface OperationalHealthAlertRepositoryInterface
{
    public function activeBusiness(): ?Business;

    /** @return Collection<int, User> */
    public function alertRecipients(int $businessId): Collection;
}
