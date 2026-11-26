<?php

namespace App\Services\Customers\Commands;

use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCustomerCommand
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $businessId, int $customerId, int $userId): void
    {
        $actor = User::query()->findOrFail($userId);
        abort_unless($actor->hasBusinessPermission($businessId, 'customers.delete'), 403);

        DB::transaction(function () use ($businessId, $customerId, $userId): void {
            $customer = $this->customers->findForBusiness($businessId, $customerId);
            $customer->newQuery()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            $hasRetainedHistory = $customer->consents()->exists()
                || $customer->visits()->exists()
                || $customer->reviews()->exists()
                || $customer->conversations()->exists()
                || $customer->scheduledMessages()->exists();

            if ($hasRetainedHistory) {
                throw ValidationException::withMessages([
                    'customer' => 'This customer has retained consent or operational history. Anonymize the record to remove personal data while preserving required history.',
                ]);
            }

            $customerId = (int) $customer->getKey();
            $customer->delete();
            $this->audit->execute($businessId, $userId, 'customer.deleted', Customer::class, $customerId, null, ['deleted' => true]);
        });
    }
}
