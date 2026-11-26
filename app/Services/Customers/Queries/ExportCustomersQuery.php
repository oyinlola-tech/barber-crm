<?php

namespace App\Services\Customers\Queries;

use App\Models\Customer;
use Illuminate\Support\LazyCollection;

class ExportCustomersQuery
{
    /** @return LazyCollection<int, Customer> */
    public function execute(int $businessId): LazyCollection
    {
        return Customer::query()
            ->where('business_id', $businessId)
            ->with(['tags:id,name', 'consents'])
            ->withCount('visits')
            ->orderBy('id')
            ->lazy(200);
    }
}
