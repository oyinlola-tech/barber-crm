<?php

namespace App\Services\Automations\Commands;

use App\Models\AutomationRule;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAutomationRuleCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(int $businessId, array $attributes, ?int $userId = null): AutomationRule
    {
        return DB::transaction(function () use ($businessId, $attributes, $userId): AutomationRule {
            $this->assertPermission($businessId, $userId, 'automations.create');
            $settings = $attributes['settings'] ?? [];
            $templateId = filter_var($settings['template_id'] ?? null, FILTER_VALIDATE_INT);
            $template = is_int($templateId)
                ? MessageTemplate::query()->where('business_id', $businessId)->find($templateId)
                : null;
            if ($template === null || $template->provider_template_id === null || strtoupper((string) $template->provider_status) !== 'APPROVED') {
                throw ValidationException::withMessages(['settings.template_id' => 'Choose a template that Meta currently reports as approved.']);
            }
            if (($attributes['is_enabled'] ?? false) && ! $template->is_enabled) {
                throw ValidationException::withMessages(['is_enabled' => 'Enable the approved template before enabling this automation.']);
            }
            if (($attributes['trigger'] ?? null) === 'customer_inactive' && ! in_array($template->purpose, ['reengagement', 'retention'], true)) {
                throw ValidationException::withMessages(['settings.template_id' => 'Inactive-customer rules require a re-engagement or retention template.']);
            }

            $rule = AutomationRule::query()->create([
                ...$attributes,
                'business_id' => $businessId,
                'is_enabled' => $attributes['is_enabled'] ?? false,
            ]);
            $this->audit->execute($businessId, $userId, 'automation_rule.created', AutomationRule::class, $rule->getKey(), null, ['trigger' => $rule->trigger, 'action' => $rule->action, 'is_enabled' => $rule->is_enabled]);

            return $rule;
        });
    }

    private function assertPermission(int $businessId, ?int $userId, string $permission): void
    {
        if ($userId === null || ! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, $permission)) {
            throw new AuthorizationException('You are not allowed to manage automation rules.');
        }
    }
}
