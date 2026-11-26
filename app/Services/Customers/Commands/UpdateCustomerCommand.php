<?php

namespace App\Services\Customers\Commands;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class UpdateCustomerCommand
{
    public function __construct(public CustomerRepositoryInterface $customers, public RecordAuditLogCommand $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(int $businessId, int $customerId, int $userId, array $attributes): Customer
    {
        return DB::transaction(function () use ($businessId, $customerId, $userId, $attributes): Customer {
            $customer = $this->customers->findForBusiness($businessId, $customerId);
            $before = $customer->only(['first_name', 'last_name', 'phone', 'email', 'date_of_birth', 'notes']);
            $customer = $this->customers->updateForBusiness($businessId, $customerId, $attributes);
            $this->audit->execute($businessId, $userId, 'customer.updated', Customer::class, $customer->getKey(), $before, $customer->only(array_keys($before)));

            return $customer;
        });
    }
}
