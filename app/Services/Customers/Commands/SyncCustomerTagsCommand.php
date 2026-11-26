<?php

namespace App\Services\Customers\Commands;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class SyncCustomerTagsCommand
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly RecordAuditLogCommand $audit,
    ) {}

    /** @param array<int, string> $names */
    public function execute(int $businessId, int $customerId, int $userId, array $names): Customer
    {
        return DB::transaction(function () use ($businessId, $customerId, $userId, $names): Customer {
            $customer = $this->customers->syncTagsForBusiness($businessId, $customerId, $names);
            $this->audit->execute($businessId, $userId, 'customer.tags_updated', Customer::class, $customerId, null, [
                'tag_ids' => $customer->tags->modelKeys(),
            ]);

            return $customer;
        });
    }
}
