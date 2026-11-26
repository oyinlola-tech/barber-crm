<?php

namespace App\Services\Customers\Commands;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestoreCustomerCommand
{
    public function __construct(public CustomerRepositoryInterface $customers, public RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $customerId, ?int $userId = null): Customer
    {
        return DB::transaction(function () use ($businessId, $customerId, $userId): Customer {
            $customer = $this->customers->findForBusiness($businessId, $customerId);
            if ($customer->status !== 'archived') {
                throw ValidationException::withMessages(['customer' => 'Only an archived customer can be restored.']);
            }
            $customer->forceFill(['status' => 'active', 'archived_at' => null])->save();
            $this->audit->execute($businessId, $userId, 'customer.restored', Customer::class, $customer->getKey(), null, ['status' => 'active']);

            return $customer->refresh();
        });
    }
}
