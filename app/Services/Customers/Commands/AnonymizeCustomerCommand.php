<?php

namespace App\Services\Customers\Commands;

use App\Enums\ScheduledMessageStatus;
use App\Interfaces\Repositories\Customers\CustomerRepositoryInterface;
use App\Models\Customer;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnonymizeCustomerCommand
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $businessId, int $customerId, int $userId): Customer
    {
        $actor = User::query()->findOrFail($userId);
        abort_unless($actor->hasBusinessPermission($businessId, 'customers.anonymize'), 403);

        return DB::transaction(function () use ($businessId, $customerId, $userId): Customer {
            $scheduled = ScheduledMessage::query()
                ->where('business_id', $businessId)
                ->where('customer_id', $customerId)
                ->lockForUpdate()
                ->get();

            $inFlight = $scheduled->contains(fn (ScheduledMessage $message): bool => in_array($message->status, [
                ScheduledMessageStatus::Processing->value,
                ScheduledMessageStatus::Sending->value,
                ScheduledMessageStatus::ReconciliationRequired->value,
                'delivery_uncertain',
            ], true));

            if ($inFlight) {
                throw ValidationException::withMessages([
                    'customer' => 'An outbound message is being sent or needs delivery reconciliation. Resolve that message before anonymizing this customer.',
                ]);
            }

            $customer = $this->customers->findForBusiness($businessId, $customerId);
            $customer->newQuery()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            $replacementPhone = '+999'.$customer->getKey();

            $scheduled->whereIn('status', [ScheduledMessageStatus::Pending->value, ScheduledMessageStatus::Queued->value])
                ->each(function (ScheduledMessage $message): void {
                    $message->forceFill(['status' => 'cancelled', 'body' => null, 'payload' => null, 'last_error' => 'Customer record anonymized.'])->save();
                });

            $conversationIds = DB::table('conversations')
                ->where('business_id', $businessId)
                ->where('customer_id', $customer->getKey())
                ->select('id');

            DB::table('messages')
                ->where('business_id', $businessId)
                ->whereIn('conversation_id', $conversationIds)
                ->update(['body' => null, 'payload' => null, 'updated_at' => now()]);

            DB::table('conversation_notes')
                ->where('business_id', $businessId)
                ->whereIn('conversation_id', $conversationIds)
                ->update(['body' => '[redacted: customer anonymized]', 'updated_at' => now()]);

            DB::table('reviews')
                ->where('business_id', $businessId)
                ->where('customer_id', $customer->getKey())
                ->update(['body' => null, 'updated_at' => now()]);

            $customer->forceFill([
                'first_name' => 'Deleted',
                'last_name' => 'Customer',
                'phone' => $replacementPhone,
                'email' => null,
                'date_of_birth' => null,
                'intended_return_date' => null,
                'notes' => null,
                'status' => 'anonymized',
                'archived_at' => now(),
            ])->save();

            $customer->noteEntries()->delete();
            $customer->tags()->detach();

            DB::table('audit_logs')
                ->where('business_id', $businessId)
                ->where('auditable_type', Customer::class)
                ->where('auditable_id', $customer->getKey())
                ->update(['before' => null, 'after' => null]);

            $this->audit->execute(
                $businessId,
                $userId,
                'customer.anonymized',
                Customer::class,
                $customer->getKey(),
                null,
                ['status' => 'anonymized', 'pending_messages_cancelled' => true],
            );

            return $customer->refresh();
        });
    }
}
