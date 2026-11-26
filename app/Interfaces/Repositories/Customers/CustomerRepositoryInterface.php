<?php

namespace App\Interfaces\Repositories\Customers;

use App\Models\Customer;
use App\Models\CustomerNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    public function paginateForBusiness(int $businessId, ?string $search = null, int $perPage = 20, string $status = 'active'): LengthAwarePaginator;

    public function findForBusiness(int $businessId, int $customerId): Customer;

    /** @param array<string, mixed> $attributes */
    public function createForBusiness(int $businessId, int $userId, array $attributes): Customer;

    /** @param array<string, mixed> $attributes */
    public function updateForBusiness(int $businessId, int $customerId, array $attributes): Customer;

    public function addNoteForBusiness(int $businessId, int $customerId, int $userId, string $body): CustomerNote;

    /** @param array<int, string> $names */
    public function syncTagsForBusiness(int $businessId, int $customerId, array $names): Customer;
}
