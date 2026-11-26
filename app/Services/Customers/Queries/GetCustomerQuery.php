<?php

namespace App\Services\Customers\Queries;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;

class GetCustomerQuery
{
    public function __construct(public CustomerRepositoryInterface $customers) {}

    public function execute(int $businessId, int $customerId): Customer
    {
        return $this->customers->findForBusiness($businessId, $customerId);
    }
}
