<?php

namespace App\Services\Customers\Commands;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveCustomerCommand
{
    public function __construct(public CustomerRepositoryInterface $customers, public RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $customerId, ?int $userId = null): Customer
    {
        return DB::transaction(function () use ($businessId, $customerId, $userId): Customer {
            $customer = $this->customers->findForBusiness($businessId, $customerId);
            if ($customer->status !== 'active') {
                throw ValidationException::withMessages(['customer' => 'Only an active customer can be archived.']);
            }
            $customer->forceFill(['status' => 'archived', 'archived_at' => now()])->save();
            $this->audit->execute($businessId, $userId, 'customer.archived', Customer::class, $customer->getKey(), null, ['status' => 'archived']);

            return $customer->refresh();
        });
    }
}
