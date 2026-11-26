<?php

namespace App\Services\Customers\Queries;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCustomersQuery
{
    public function __construct(public CustomerRepositoryInterface $customers) {}

    public function execute(int $businessId, ?string $search = null, int $perPage = 20, string $status = 'active'): LengthAwarePaginator
    {
        return $this->customers->paginateForBusiness($businessId, $search, min(max($perPage, 1), 100), $status);
    }
}
