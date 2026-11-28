<?php

namespace App\Services\Settings\Commands;

use App\Enums\ScheduledMessageStatus;
use App\Enums\WhatsAppMessageFeature;
use App\Models\AutomationRule;
use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use App\Services\Settings\Queries\GetWhatsAppMessageFeaturesQuery;
use Illuminate\Support\Facades\DB;

class CancelScheduledMessagesForDisabledFeaturesCommand
{
    public function __construct(private readonly GetWhatsAppMessageFeaturesQuery $features) {}

    public function execute(int $businessId): int
    {
        $enabledFeatures = $this->features->execute($businessId);
        if (! ($enabledFeatures[WhatsAppMessageFeature::Outbound->value] ?? false)) {
            return $this->cancelAll($businessId);
        }
        $messages = ScheduledMessage::query()
            ->where('business_id', $businessId)
            ->where('channel', 'whatsapp')
            ->whereIn('status', [ScheduledMessageStatus::Pending->value, ScheduledMessageStatus::Queued->value])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $templateIds = $messages->map(fn (ScheduledMessage $message): int => (int) ($message->payload['template_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)->unique()->values();
        $purposes = MessageTemplate::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $templateIds)
            ->pluck('purpose', 'id');
        $ruleIds = $messages->pluck('automation_rule_id')->filter()->unique()->values();
        $triggers = AutomationRule::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $ruleIds)
            ->pluck('trigger', 'id');
        $cancelled = 0;

        foreach ($messages as $message) {
            $payload = $message->payload ?? [];
            $purpose = $purposes->get((int) ($payload['template_id'] ?? 0));
            $trigger = $payload['trigger'] ?? $triggers->get($message->automation_rule_id);
            if (! is_string($purpose) || ! is_string($trigger)) {
                continue;
            }
            $feature = match ($trigger) {
                'customer_inactive' => WhatsAppMessageFeature::InactiveReengagement,
                'return_date_missed' => WhatsAppMessageFeature::MissedVisitFollowUp,
                default => WhatsAppMessageFeature::forTemplatePurpose($purpose) ?? WhatsAppMessageFeature::ExperienceFollowUp,
            };
            if ($enabledFeatures[$feature->value] ?? false) {
                continue;
            }

            $message->update([
                'status' => ScheduledMessageStatus::Cancelled->value,
                'claimed_at' => null,
                'last_error' => 'WhatsApp feature was disabled in business messaging settings.',
            ]);
            $cancelled++;
        }

        return $cancelled;
    }

    private function cancelAll(int $businessId): int
    {
        return DB::table('scheduled_messages')
            ->where('business_id', $businessId)
            ->where('channel', 'whatsapp')
            ->whereIn('status', [ScheduledMessageStatus::Pending->value, ScheduledMessageStatus::Queued->value])
            ->update([
                'status' => ScheduledMessageStatus::Cancelled->value,
                'claimed_at' => null,
                'last_error' => 'All outgoing WhatsApp messaging was disabled in business settings.',
                'updated_at' => now(),
            ]);
    }
}
