<?php

namespace App\Services\Automations\Commands;

use App\Models\AutomationRule;
use App\Models\ScheduledMessage;
use App\Services\Audit\Commands\RecordAuditLogCommand;

class DisableAutomationsForTemplateCommand
{
    public function __construct(private readonly RecordAuditLogCommand $audit) {}

    /** @return array{rules:int,messages:int} */
    public function execute(int $businessId, int $templateId, int $actorId, string $reason): array
    {
        $rules = AutomationRule::query()
            ->where('business_id', $businessId)
            ->where('is_enabled', true)
            ->whereJsonContains('settings', ['template_id' => $templateId])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($rules->isEmpty()) {
            return ['rules' => 0, 'messages' => 0];
        }

        foreach ($rules as $rule) {
            $rule->update(['is_enabled' => false]);
            $this->audit->execute(
                $businessId,
                $actorId,
                'automation_rule.disabled_template_unavailable',
                AutomationRule::class,
                (int) $rule->getKey(),
                ['is_enabled' => true],
                ['is_enabled' => false, 'template_id' => $templateId, 'reason' => $reason],
            );
        }

        $messages = ScheduledMessage::query()
            ->where('business_id', $businessId)
            ->whereIn('automation_rule_id', $rules->modelKeys())
            ->whereIn('status', ['pending', 'queued'])
            ->lockForUpdate()
            ->get();

        foreach ($messages as $message) {
            $message->update([
                'status' => 'cancelled',
                'last_error' => 'The associated automation was disabled because its WhatsApp template is unavailable.',
            ]);
        }

        return ['rules' => $rules->count(), 'messages' => $messages->count()];
    }
}
