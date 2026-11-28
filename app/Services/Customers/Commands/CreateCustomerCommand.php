<?php

namespace App\Services\Customers\Commands;

use App\Dtos\Customers\CreateCustomerDto;
use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class CreateCustomerCommand
{
    public function __construct(public CustomerRepositoryInterface $customers, public RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $userId, CreateCustomerDto $customerData): Customer
    {
        $actor = User::query()->findOrFail($userId);
        abort_unless($actor->hasBusinessPermission($businessId, 'customers.create'), 403);
        if ($customerData->notes !== null) {
            abort_unless($actor->hasBusinessPermission($businessId, 'customers.note'), 403);
        }

        return DB::transaction(function () use ($businessId, $userId, $customerData): Customer {
            $customer = $this->customers->createForBusiness($businessId, $userId, [
                'first_name' => $customerData->firstName,
                'last_name' => $customerData->lastName,
                'phone' => $customerData->phone,
                'email' => $customerData->email,
                'date_of_birth' => $customerData->dateOfBirth,
                'intended_return_date' => $customerData->intendedReturnDate,
                'intended_return_status' => $customerData->intendedReturnDate === null ? null : 'expected',
                'notes' => $customerData->notes,
            ]);
            $this->audit->execute($businessId, $userId, 'customer.created', Customer::class, $customer->getKey(), null, $customer->only(['first_name', 'last_name', 'phone', 'email']));

            return $customer;
        });
    }
}
