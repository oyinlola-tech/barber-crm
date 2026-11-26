<?php

namespace App\Services\Automations\Commands;

use App\Enums\ScheduledMessageStatus;
use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use App\Services\Automations\SendWindowCalculator;
use App\Services\Consent\Queries\CustomerCanReceiveMessageQuery;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScheduleFollowUpCommand
{
    public function __construct(
        public CustomerCanReceiveMessageQuery $consent,
        public SendWindowCalculator $sendWindow,
    ) {}

    /** @param array<string, mixed> $payload */
    public function execute(int $businessId, int $customerId, int $ruleId, \DateTimeInterface $scheduledAt, array $payload, string $idempotencyKey): ScheduledMessage
    {
        if (trim($idempotencyKey) === '' || mb_strlen($idempotencyKey) > 128) {
            throw new InvalidArgumentException('The scheduled message idempotency key must contain between 1 and 128 characters.');
        }

        return DB::transaction(function () use ($businessId, $customerId, $ruleId, $scheduledAt, $payload, $idempotencyKey): ScheduledMessage {
            $customer = Customer::query()
                ->where('business_id', $businessId)
                ->where('status', 'active')
                ->whereNull('archived_at')
                ->findOrFail($customerId);
            $rule = AutomationRule::query()
                ->where('business_id', $businessId)
                ->where('is_enabled', true)
                ->findOrFail($ruleId);
            $settings = $rule->settings ?? [];
            $templateId = filter_var($settings['template_id'] ?? null, FILTER_VALIDATE_INT);

            if (! is_int($templateId) || $templateId < 1) {
                throw new InvalidArgumentException('The automation rule must reference an approved message template.');
            }

            $template = MessageTemplate::query()
                ->where('business_id', $businessId)
                ->whereIn('status', ['submitted', 'synchronized'])
                ->where('provider_status', 'APPROVED')
                ->where('is_enabled', true)
                ->findOrFail($templateId);

            if (! $this->consent->execute($businessId, $customerId, 'whatsapp')) {
                throw new InvalidArgumentException('The customer does not have active WhatsApp messaging consent.');
            }
            if ($rule->trigger === 'customer_inactive' && ! in_array($template->purpose, ['reengagement', 'retention'], true)) {
                throw new InvalidArgumentException('Inactive-customer follow-ups require a re-engagement or retention template.');
            }

            $business = Business::query()->findOrFail($businessId);
            $variables = $template->variable_map ?? [];
            $followUpNumber = max(1, min((int) ($payload['follow_up_number'] ?? 1), 20));

            $normalizedPayload = [
                'template_id' => $template->getKey(),
                'template_name' => $template->name,
                'language' => $template->language,
                'variables' => $variables,
                'follow_up_number' => $followUpNumber,
                'visit_id' => isset($payload['visit_id']) ? (int) $payload['visit_id'] : null,
                'visit_revision' => isset($payload['visit_revision']) ? (int) $payload['visit_revision'] : null,
                'inactivity_anchor' => is_string($payload['inactivity_anchor'] ?? null) ? $payload['inactivity_anchor'] : null,
                'trigger' => is_string($payload['trigger'] ?? null) ? $payload['trigger'] : $rule->trigger,
                'max_attempts' => max(1, min((int) ($settings['max_attempts'] ?? config('automation.max_attempts')), 10)),
            ];
            $scheduledTime = $this->sendWindow->nextAllowedAt(
                $scheduledAt,
                $business->timezone,
                is_array($settings['sending_hours'] ?? null) ? $settings['sending_hours'] : null,
            );

            $scheduledMessage = ScheduledMessage::query()->firstOrCreate([
                'business_id' => $businessId,
                'idempotency_key' => $idempotencyKey,
            ], [
                'customer_id' => $customer->getKey(),
                'automation_rule_id' => $rule->getKey(),
                'channel' => 'whatsapp',
                'payload' => $normalizedPayload,
                'status' => ScheduledMessageStatus::Pending->value,
                'scheduled_at' => $scheduledTime,
            ]);

            if ((int) $scheduledMessage->customer_id !== $customerId || (int) $scheduledMessage->automation_rule_id !== $ruleId) {
                throw new \LogicException('The idempotency key is already assigned to a different automation operation.');
            }

            return $scheduledMessage;
        });
    }
}
