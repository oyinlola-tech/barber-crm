<?php

namespace App\Services\Customers\Queries;

use App\Models\Business;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class CustomerHasUpcomingReturnDateQuery
{
    public function execute(int $businessId, Customer $customer): bool
    {
        if ((int) $customer->business_id !== $businessId || $customer->intended_return_date === null) {
            return false;
        }

        $business = Business::query()->find($businessId);
        if ($business === null) {
            return false;
        }

        $returnDateValue = $customer->getAttribute('intended_return_date');
        if (! is_string($returnDateValue) && ! $returnDateValue instanceof Carbon) {
            return false;
        }

        $returnDate = CarbonImmutable::parse($returnDateValue, $business->timezone);

        return $returnDate->startOfDay()->greaterThanOrEqualTo(now($business->timezone)->startOfDay());
    }
}
