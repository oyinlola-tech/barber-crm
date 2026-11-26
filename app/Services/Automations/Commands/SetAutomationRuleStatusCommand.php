<?php

namespace App\Services\Automations\Commands;

use App\Models\AutomationRule;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetAutomationRuleStatusCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $ruleId, bool $isEnabled, ?int $userId = null): AutomationRule
    {
        return DB::transaction(function () use ($businessId, $ruleId, $isEnabled, $userId): AutomationRule {
            $permission = $isEnabled ? 'automations.enable' : 'automations.disable';
            if ($userId === null || ! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, $permission)) {
                throw new AuthorizationException('You are not allowed to manage automation rules.');
            }

            $rule = AutomationRule::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($ruleId);
            $settings = $rule->settings ?? [];
            $template = MessageTemplate::query()
                ->where('business_id', $businessId)
                ->where('provider_status', 'APPROVED')
                ->find($settings['template_id'] ?? null);
            if ($isEnabled && ($template === null || ! $template->is_enabled || $template->provider_template_id === null)) {
                throw ValidationException::withMessages(['is_enabled' => 'Enable a Meta-approved message template before enabling this automation.']);
            }
            if ($isEnabled && $rule->trigger === 'customer_inactive' && ! in_array($template->purpose, ['reengagement', 'retention'], true)) {
                throw ValidationException::withMessages(['is_enabled' => 'Inactive-customer rules require a re-engagement or retention template.']);
            }

            $before = ['is_enabled' => $rule->is_enabled];
            $rule->update(['is_enabled' => $isEnabled]);
            $this->audit->execute($businessId, $userId, 'automation_rule.status_updated', AutomationRule::class, $rule->getKey(), $before, ['is_enabled' => $isEnabled]);

            return $rule->refresh();
        });
    }
}
