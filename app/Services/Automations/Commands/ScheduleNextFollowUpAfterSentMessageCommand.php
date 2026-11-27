<?php

namespace App\Services\Automations\Commands;

use App\Enums\ScheduledMessageStatus;
use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use App\Services\Customers\Queries\CustomerHasUpcomingReturnDateQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ScheduleNextFollowUpAfterSentMessageCommand
{
    public function __construct(
        private readonly ScheduleFollowUpCommand $scheduleFollowUp,
        private readonly CustomerCanReceiveMessageQuery $consent,
        private readonly CustomerHasUpcomingReturnDateQuery $upcomingReturnDate,
    ) {}

    public function execute(int $scheduledMessageId): bool
    {
        return DB::transaction(function () use ($scheduledMessageId): bool {
            $message = ScheduledMessage::query()->lockForUpdate()->findOrFail($scheduledMessageId);
            if ($message->status !== ScheduledMessageStatus::Sent->value
                || $message->sent_at === null
                || $message->follow_up_reconciled_at !== null) {
                return false;
            }

            $customer = Customer::query()
                ->where('business_id', $message->business_id)
                ->lockForUpdate()
                ->find($message->customer_id);
            $rule = $message->automation_rule_id === null
                ? null
                : AutomationRule::query()
                    ->where('business_id', $message->business_id)
                    ->find($message->automation_rule_id);

            if ($this->canScheduleNextFollowUp($message, $customer, $rule)) {
                $this->scheduleFollowUp($message, $customer, $rule);
            }

            // The next schedule and this marker share one transaction. If the
            // process stops before commit, recovery retries with the same key.
            $message->update(['follow_up_reconciled_at' => now()]);

            return true;
        });
    }

    private function canScheduleNextFollowUp(ScheduledMessage $message, ?Customer $customer, ?AutomationRule $rule): bool
    {
        if ($customer === null || $customer->status !== 'active' || $customer->archived_at !== null
            || $rule === null || ! $rule->is_enabled
            || ! $this->consent->execute($message->business_id, $customer->getKey(), $message->channel)) {
            return false;
        }

        $payload = $message->payload ?? [];
        $templatePurpose = MessageTemplate::query()
            ->where('business_id', $message->business_id)
            ->whereKey($payload['template_id'] ?? 0)
            ->value('purpose');
        if (is_string($templatePurpose)
            && in_array($templatePurpose, config('automation.return_reminder_template_purposes', []), true)
            && $this->upcomingReturnDate->execute((int) $message->business_id, $customer)) {
            return false;
        }

        $currentStep = (int) ($payload['follow_up_number'] ?? 1);
        $visitId = (int) ($payload['visit_id'] ?? 0);
        $trigger = $payload['trigger'] ?? $rule->trigger;
        if ($visitId < 1 && $trigger !== 'customer_inactive') {
            return false;
        }

        $settings = $rule->settings ?? [];
        $maxFollowUps = max(1, min((int) ($settings['max_follow_ups'] ?? 1), 20));
        if ($currentStep >= $maxFollowUps || (int) ($settings['repeat_interval_minutes'] ?? 0) < 1) {
            return false;
        }

        if ($message->conversation_id !== null) {
            $conversation = Conversation::query()
                ->where('business_id', $message->business_id)
                ->find($message->conversation_id);
            if ($conversation?->last_customer_message_at !== null
                && $conversation->last_customer_message_at->greaterThanOrEqualTo($message->sent_at)) {
                return false;
            }
        }

        if ($trigger === 'customer_inactive') {
            return $this->inactiveCustomerStillEligible($message, $customer, $rule);
        }

        $latestVisitId = $customer->visits()
            ->orderByDesc('visited_at')
            ->orderByDesc('id')
            ->value('id');

        return $latestVisitId !== null && (int) $latestVisitId === $visitId;
    }

    private function inactiveCustomerStillEligible(ScheduledMessage $message, Customer $customer, AutomationRule $rule): bool
    {
        $payload = $message->payload ?? [];
        $anchorValue = $payload['inactivity_anchor'] ?? null;
        $inactiveDays = filter_var(($rule->settings ?? [])['inactive_after_days'] ?? null, FILTER_VALIDATE_INT);
        if (! is_string($anchorValue) || ! is_int($inactiveDays) || $inactiveDays < 3 || $inactiveDays > 365) {
            return false;
        }

        try {
            $anchor = CarbonImmutable::parse($anchorValue, 'UTC')->utc();
        } catch (Throwable) {
            return false;
        }

        $lastVisitAt = $customer->visits()->max('visited_at');
        $business = Business::query()->find($message->business_id);
        if ($lastVisitAt === null || $business === null
            || ! CarbonImmutable::parse($lastVisitAt, 'UTC')->utc()->equalTo($anchor)
            || $anchor->addDays($inactiveDays)->isFuture()) {
            return false;
        }

        return $customer->intended_return_date === null
            || CarbonImmutable::parse($customer->intended_return_date)->lt(now($business->timezone)->startOfDay());
    }

    private function scheduleFollowUp(ScheduledMessage $message, Customer $customer, AutomationRule $rule): void
    {
        $payload = $message->payload ?? [];
        $currentStep = (int) ($payload['follow_up_number'] ?? 1);
        $visitId = (int) ($payload['visit_id'] ?? 0);
        $trigger = $payload['trigger'] ?? $rule->trigger;
        $nextPayload = [
            'follow_up_number' => $currentStep + 1,
            'visit_id' => $visitId > 0 ? $visitId : null,
            'trigger' => $trigger,
        ];

        if ($trigger === 'customer_inactive') {
            $nextPayload['inactivity_anchor'] = $payload['inactivity_anchor'];
        }

        $visitRevision = (int) ($payload['visit_revision'] ?? 1);
        $idempotencyKey = $visitRevision === 1
            ? 'visit:'.$visitId.':rule:'.$rule->getKey().':step:'.($currentStep + 1)
            : 'visit:'.$visitId.':revision:'.$visitRevision.':rule:'.$rule->getKey().':step:'.($currentStep + 1);

        if ($trigger === 'customer_inactive') {
            preg_match('/cycle:(\d+)/', $message->idempotency_key, $matches);
            $cycle = isset($matches[1]) ? (int) $matches[1] : 0;
            $anchor = CarbonImmutable::parse((string) $payload['inactivity_anchor'])->utc()->format('YmdHis');
            $idempotencyKey = 'inactive:customer:'.$customer->getKey().':rule:'.$rule->getKey().':visit:'.$anchor.':cycle:'.$cycle.':step:'.($currentStep + 1);
        }

        $this->scheduleFollowUp->execute(
            (int) $message->business_id,
            (int) $customer->getKey(),
            (int) $rule->getKey(),
            now('UTC')->addMinutes((int) $rule->settings['repeat_interval_minutes']),
            $nextPayload,
            $idempotencyKey,
        );
    }
}
