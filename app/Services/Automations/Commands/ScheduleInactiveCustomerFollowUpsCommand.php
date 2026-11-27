<?php

namespace App\Services\Automations\Commands;

use App\Enums\AutomationTrigger;
use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Customer;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use App\Services\Customers\Queries\CustomerHasUpcomingReturnDateQuery;
use Carbon\CarbonImmutable;

class ScheduleInactiveCustomerFollowUpsCommand
{
    public function __construct(
        private readonly ScheduleFollowUpCommand $scheduleFollowUp,
        private readonly CustomerCanReceiveMessageQuery $consent,
        private readonly CustomerHasUpcomingReturnDateQuery $upcomingReturnDate,
    ) {}

    public function execute(int $limit = 100): int
    {
        if (! config('automation.enabled')) {
            return 0;
        }

        $scheduledCount = 0;
        $maximum = min(max($limit, 1), 500);
        $rules = AutomationRule::query()
            ->where('trigger', AutomationTrigger::CustomerInactive->value)
            ->where('is_enabled', true)
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if ($scheduledCount >= $maximum) {
                break;
            }
            $settings = $rule->settings ?? [];
            $inactiveDays = filter_var($settings['inactive_after_days'] ?? null, FILTER_VALIDATE_INT);
            if (! is_int($inactiveDays) || $inactiveDays < 3 || $inactiveDays > 365) {
                continue;
            }

            $business = Business::query()->find($rule->business_id);
            if ($business === null) {
                continue;
            }

            $cutoff = now('UTC')->subDays($inactiveDays);
            $intervalHours = max(1, min((int) config('automation.minimum_customer_message_interval_hours', 72), 720));
            $localToday = now($business->timezone)->toDateString();
            Customer::query()
                ->where('business_id', $rule->business_id)
                ->where('status', 'active')
                ->whereNull('archived_at')
                ->whereHas('visits', fn ($query) => $query->where('visited_at', '<=', $cutoff))
                ->whereDoesntHave('visits', fn ($query) => $query->where('visited_at', '>', $cutoff))
                ->where(function ($query) use ($localToday): void {
                    $query->whereNull('intended_return_date')->orWhereDate('intended_return_date', '<', $localToday);
                })
                ->whereHas('consents', function ($query): void {
                    $query->where('channel', 'whatsapp')->where('granted', true)->whereNull('revoked_at')
                        ->whereRaw('customer_consents.id = (select latest_consent.id from customer_consents as latest_consent where latest_consent.business_id = customers.business_id and latest_consent.customer_id = customers.id and latest_consent.channel = ? order by latest_consent.recorded_at desc, latest_consent.id desc limit 1)', ['whatsapp']);
                })
                ->whereDoesntHave('conversations', fn ($query) => $query->where('last_customer_message_at', '>=', now()->subHours($intervalHours)))
                ->withMax('visits', 'visited_at')
                ->whereDoesntHave('scheduledMessages', function ($query) use ($intervalHours): void {
                    $query->where(function ($query) use ($intervalHours): void {
                        $query->whereIn('status', ['pending', 'queued', 'sending', 'reconciliation_required'])
                            ->orWhere('sent_at', '>=', now()->subHours($intervalHours));
                    });
                })
                ->orderBy('id')
                ->limit($maximum - $scheduledCount)
                ->get()
                ->each(function (Customer $customer) use ($rule, $settings, $business, &$scheduledCount): void {
                    if ($this->upcomingReturnDate->execute((int) $business->getKey(), $customer)
                        || ! $this->consent->execute((int) $rule->business_id, (int) $customer->getKey(), 'whatsapp')
                        || $this->hasRecentCustomerReply($customer, (int) $rule->business_id)) {
                        return;
                    }

                    $lastVisitAt = $customer->getAttribute('visits_max_visited_at');
                    if (! is_string($lastVisitAt) && ! $lastVisitAt instanceof \DateTimeInterface) {
                        return;
                    }
                    $anchor = CarbonImmutable::parse($lastVisitAt, 'UTC')->utc();
                    $inactiveDays = (int) ($settings['inactive_after_days'] ?? 30);
                    $cycleDays = max(7, min((int) ($settings['reengagement_cycle_days'] ?? 30), 365));
                    $inactiveSince = $anchor->addDays($inactiveDays);
                    $daysInactive = max(0, (int) $inactiveSince->diffInDays(now('UTC')));
                    $cycle = intdiv($daysInactive, $cycleDays);
                    $idempotencyKey = 'inactive:customer:'.$customer->getKey().':rule:'.$rule->getKey().':visit:'.$anchor->format('YmdHis').':cycle:'.$cycle.':step:1';
                    $scheduled = $this->scheduleFollowUp->execute(
                        (int) $rule->business_id,
                        (int) $customer->getKey(),
                        (int) $rule->getKey(),
                        now('UTC')->addMinutes((int) $rule->delay_minutes),
                        [
                            'follow_up_number' => 1,
                            'inactivity_anchor' => $anchor->toIso8601String(),
                            'trigger' => AutomationTrigger::CustomerInactive->value,
                        ],
                        $idempotencyKey,
                    );

                    if ($scheduled->wasRecentlyCreated) {
                        $scheduledCount++;
                    }
                });
        }

        return $scheduledCount;
    }

    private function hasRecentCustomerReply(Customer $customer, int $businessId): bool
    {
        return $customer->conversations()->where('business_id', $businessId)
            ->where('last_customer_message_at', '>=', now()->subHours(max(1, min((int) config('automation.minimum_customer_message_interval_hours', 72), 720))))
            ->exists();
    }
}
