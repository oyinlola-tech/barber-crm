<?php

namespace App\Listeners\Automations;

use App\Enums\AutomationTrigger;
use App\Events\Visits\VisitCompleted;
use App\Models\AutomationRule;
use App\Models\Visit;
use App\Services\Automations\Commands\CancelCustomerFollowUpsCommand;
use App\Services\Automations\Commands\ScheduleFollowUpCommand;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;

class ScheduleFollowUpAfterVisit
{
    public function __construct(
        public ScheduleFollowUpCommand $scheduleFollowUp,
        public CancelCustomerFollowUpsCommand $cancelCustomerFollowUps,
        public CustomerCanReceiveMessageQuery $consent,
    ) {}

    public function handle(VisitCompleted $event): void
    {
        $this->schedule($event->visit);
    }

    public function schedule(Visit $visit): void
    {
        $this->cancelCustomerFollowUps->execute($visit->business_id, $visit->customer_id);

        if (! $this->consent->execute($visit->business_id, $visit->customer_id, 'whatsapp')) {
            return;
        }

        $rules = AutomationRule::query()
            ->where('business_id', $visit->business_id)
            ->where('trigger', AutomationTrigger::VisitCompleted->value)
            ->where('is_enabled', true)
            ->get();

        foreach ($rules as $rule) {
            $settings = $rule->settings ?? [];
            if (! is_numeric($settings['template_id'] ?? null)) {
                continue;
            }

            $idempotencyKey = $visit->automation_revision === 1
                ? 'visit:'.$visit->getKey().':rule:'.$rule->getKey().':step:1'
                : 'visit:'.$visit->getKey().':revision:'.$visit->automation_revision.':rule:'.$rule->getKey().':step:1';

            $this->scheduleFollowUp->execute(
                $visit->business_id,
                $visit->customer_id,
                $rule->getKey(),
                now('UTC')->addMinutes($rule->delay_minutes),
                ['follow_up_number' => 1, 'visit_id' => $visit->getKey(), 'visit_revision' => $visit->automation_revision],
                $idempotencyKey,
            );
        }
    }
}
