<?php

namespace App\Services\Customers\Commands;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCustomerCommand
{
    public function __construct(public CustomerRepositoryInterface $customers, public RecordAuditLogCommand $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(int $businessId, int $customerId, int $userId, array $attributes): Customer
    {
        $actor = User::query()->findOrFail($userId);
        abort_unless($actor->hasBusinessPermission($businessId, 'customers.update'), 403);
        $allowedAttributes = ['first_name', 'last_name', 'phone', 'email', 'date_of_birth', 'intended_return_date', 'notes'];

        if (array_diff(array_keys($attributes), $allowedAttributes) !== []) {
            throw ValidationException::withMessages(['customer' => 'The update contains fields that cannot be changed.']);
        }
        if (array_key_exists('notes', $attributes)) {
            abort_unless($actor->hasBusinessPermission($businessId, 'customers.note'), 403);
        }

        return DB::transaction(function () use ($businessId, $customerId, $userId, $attributes): Customer {
            $customer = $this->customers->findForBusiness($businessId, $customerId);
            $before = $customer->only(['first_name', 'last_name', 'phone', 'email', 'date_of_birth', 'notes']);
            $customer = $this->customers->updateForBusiness($businessId, $customerId, $attributes);
            $this->audit->execute($businessId, $userId, 'customer.updated', Customer::class, $customer->getKey(), $before, $customer->only(array_keys($before)));

            return $customer;
        });
    }
}
