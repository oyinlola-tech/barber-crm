<?php

namespace App\Services\Customers\Commands;

use App\Enums\AutomationTrigger;
use App\Enums\CustomerReturnIntentionStatus;
use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Services\Automations\Commands\ScheduleFollowUpCommand;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use App\Services\Visits\Commands\RecordVisitCommand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateReturnIntentionStatusCommand
{
    public function __construct(
        private readonly RecordAuditLogCommand $audit,
        private readonly RecordVisitCommand $recordVisit,
        private readonly ScheduleFollowUpCommand $scheduleFollowUp,
        private readonly CustomerCanReceiveMessageQuery $consent,
    ) {}

    public function execute(int $businessId, int $customerId, User $actor, CustomerReturnIntentionStatus $status): Customer
    {
        $permission = $status === CustomerReturnIntentionStatus::Arrived ? 'visits.create' : 'visits.update';
        abort_unless($actor->hasBusinessPermission($businessId, $permission), 403);

        return DB::transaction(function () use ($businessId, $customerId, $actor, $status): Customer {
            $customer = Customer::query()
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->findOrFail($customerId);
            if ($customer->status !== 'active' || $customer->intended_return_date === null) {
                throw ValidationException::withMessages(['status' => 'An active customer with a stated return date is required.']);
            }

            $business = Business::query()->findOrFail($businessId);
            $returnDate = CarbonImmutable::parse($customer->intended_return_date)->toDateString();
            if ($status === CustomerReturnIntentionStatus::Missed
                && CarbonImmutable::parse($returnDate)->greaterThan(now($business->timezone)->startOfDay())) {
                throw ValidationException::withMessages(['status' => 'Mark this return date as missed after the date has passed.']);
            }
            if ($customer->intended_return_status === $status->value) {
                return $customer;
            }
            $previousStatus = $customer->intended_return_status;
            if ($status === CustomerReturnIntentionStatus::Missed && $previousStatus === CustomerReturnIntentionStatus::Arrived->value) {
                throw ValidationException::withMessages(['status' => 'A completed visit cannot be marked as missed.']);
            }

            if ($status === CustomerReturnIntentionStatus::Arrived) {
                $this->recordVisit->execute($businessId, $actor, [
                    'customer_id' => $customerId,
                    'visited_at' => now($business->timezone)->format('Y-m-d\\TH:i'),
                    'service_ids' => [],
                    'notes' => 'Recorded from the customer return-date attendance check.',
                ]);
            }

            $customer->update(['intended_return_status' => $status->value]);
            $this->audit->execute(
                $businessId,
                (int) $actor->getKey(),
                'customer.return_intention_status_updated',
                Customer::class,
                $customerId,
                ['status' => $previousStatus],
                ['status' => $status->value, 'intended_return_date' => $returnDate],
            );

            if ($status === CustomerReturnIntentionStatus::Missed
                && $this->consent->execute($businessId, $customerId, 'whatsapp')) {
                $rules = AutomationRule::query()
                    ->where('business_id', $businessId)
                    ->where('trigger', AutomationTrigger::ReturnDateMissed->value)
                    ->where('is_enabled', true)
                    ->get();

                foreach ($rules as $rule) {
                    $this->scheduleFollowUp->execute(
                        $businessId,
                        $customerId,
                        (int) $rule->getKey(),
                        now('UTC')->addMinutes($rule->delay_minutes),
                        ['trigger' => AutomationTrigger::ReturnDateMissed->value, 'return_intention_date' => $returnDate],
                        'return-missed:'.$customerId.':'.$returnDate.':rule:'.$rule->getKey(),
                    );
                }
            }

            return $customer->refresh();
        });
    }
}
