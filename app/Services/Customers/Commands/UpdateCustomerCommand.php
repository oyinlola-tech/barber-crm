<?php

namespace App\Services\Customers\Commands;

use App\Events\Customers\CustomerReturnIntentionRecorded;
use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Services\Automations\Commands\CancelCustomerReturnReminderMessagesCommand;
use App\Services\Customers\Queries\CustomerHasUpcomingReturnDateQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCustomerCommand
{
    public function __construct(
        public CustomerRepositoryInterface $customers,
        public RecordAuditLogCommand $audit,
        private readonly CustomerHasUpcomingReturnDateQuery $upcomingReturnDate,
        private readonly CancelCustomerReturnReminderMessagesCommand $cancelReturnReminders,
    ) {}

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
            $auditFields = array_values(array_diff(array_keys($attributes), ['notes']));
            $before = $customer->only($auditFields);
            $previousReturnDate = $customer->intended_return_date === null
                ? null
                : CarbonImmutable::parse($customer->intended_return_date)->toDateString();
            if (array_key_exists('intended_return_date', $attributes)) {
                $before['intended_return_date'] = $previousReturnDate;
                $before['intended_return_status'] = $customer->intended_return_status;
                $nextReturnDate = $attributes['intended_return_date'] === null
                    ? null
                    : CarbonImmutable::parse($attributes['intended_return_date'])->toDateString();
                if ($nextReturnDate !== $previousReturnDate) {
                    $attributes['intended_return_status'] = $nextReturnDate === null ? null : 'expected';
                }
            }
            $customer = $this->customers->updateForBusiness($businessId, $customerId, $attributes);
            $after = $customer->only($auditFields);
            if (array_key_exists('intended_return_date', $attributes)) {
                $after['intended_return_status'] = $customer->intended_return_status;
            }
            $returnDate = $customer->intended_return_date === null
                ? null
                : CarbonImmutable::parse($customer->intended_return_date)->toDateString();
            if (array_key_exists('intended_return_date', $attributes) && $this->upcomingReturnDate->execute($businessId, $customer)) {
                $after['cancelled_return_reminders'] = $this->cancelReturnReminders->execute($businessId, $customerId);
            }
            $this->audit->execute($businessId, $userId, 'customer.updated', Customer::class, $customer->getKey(), $before, $after);

            if (array_key_exists('intended_return_date', $attributes)
                && $returnDate !== null
                && $returnDate !== $previousReturnDate) {
                CustomerReturnIntentionRecorded::dispatch($customer, $userId, $returnDate);
            }

            return $customer;
        });
    }
}
