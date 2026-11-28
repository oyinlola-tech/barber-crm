<?php

namespace App\Services\Automations\Commands;

use App\Enums\AutomationTrigger;
use App\Models\AutomationRule;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateAutomationRuleCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(int $businessId, int $ruleId, int $userId, array $attributes): AutomationRule
    {
        return DB::transaction(function () use ($businessId, $ruleId, $userId, $attributes): AutomationRule {
            if (! User::query()->findOrFail($userId)->hasBusinessPermission($businessId, 'automations.update')) {
                throw new AuthorizationException('You are not allowed to manage automation rules.');
            }
            $user = User::query()->findOrFail($userId);
            if (array_key_exists('is_enabled', $attributes)) {
                $statusPermission = $attributes['is_enabled'] ? 'automations.enable' : 'automations.disable';
                if (! $user->hasBusinessPermission($businessId, $statusPermission)) {
                    throw new AuthorizationException('You are not allowed to change this automation status.');
                }
            }

            $rule = AutomationRule::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($ruleId);
            $settings = $attributes['settings'] ?? $rule->settings ?? [];
            $trigger = $attributes['trigger'] ?? $rule->trigger;
            $maxFollowUps = filter_var($settings['max_follow_ups'] ?? 1, FILTER_VALIDATE_INT);
            $repeatInterval = filter_var($settings['repeat_interval_minutes'] ?? 0, FILTER_VALIDATE_INT);
            $enabled = ($attributes['is_enabled'] ?? $rule->is_enabled) === true;
            if ($enabled
                && is_int($maxFollowUps)
                && $maxFollowUps > 1
                && (! is_int($repeatInterval) || $repeatInterval < 1)) {
                throw ValidationException::withMessages([
                    'settings.repeat_interval_minutes' => 'Set a repeat interval of at least one minute when enabling multiple follow-ups.',
                ]);
            }

            if ($trigger === AutomationTrigger::CustomerInactive->value) {
                $inactiveDays = filter_var($settings['inactive_after_days'] ?? null, FILTER_VALIDATE_INT);
                if (! is_int($inactiveDays) || $inactiveDays < 3 || $inactiveDays > 365) {
                    throw ValidationException::withMessages(['settings.inactive_after_days' => 'Set an inactivity threshold between 3 and 365 days.']);
                }
            }
            $templateId = filter_var($settings['template_id'] ?? null, FILTER_VALIDATE_INT);
            $template = is_int($templateId)
                ? MessageTemplate::query()->where('business_id', $businessId)->find($templateId)
                : null;
            if ($template === null || $template->provider_template_id === null || strtoupper((string) $template->provider_status) !== 'APPROVED') {
                throw ValidationException::withMessages(['settings.template_id' => 'Choose a template that Meta currently reports as approved.']);
            }

            $enabling = ($attributes['is_enabled'] ?? $rule->is_enabled) === true;
            if ($enabling && ! $template->is_enabled) {
                throw ValidationException::withMessages(['is_enabled' => 'Enable the approved template before enabling this automation.']);
            }
            $allowedPurposes = match ($trigger) {
                AutomationTrigger::CustomerInactive->value => ['reengagement', 'retention'],
                AutomationTrigger::ReturnDateMissed->value => ['reengagement', 'retention', 'return_intention'],
                default => null,
            };
            if ($allowedPurposes !== null && ! in_array($template->purpose, $allowedPurposes, true)) {
                throw ValidationException::withMessages(['settings.template_id' => 'Choose a template whose purpose matches this follow-up rule.']);
            }

            $before = [
                'name' => $rule->name,
                'trigger' => $rule->trigger,
                'action' => $rule->action,
                'delay_minutes' => $rule->delay_minutes,
                'settings' => $rule->settings,
                'is_enabled' => $rule->is_enabled,
            ];
            $rule->update($attributes);
            $this->audit->execute($businessId, $userId, 'automation_rule.updated', AutomationRule::class, $rule->getKey(), $before, [
                'name' => $rule->name,
                'trigger' => $rule->trigger,
                'action' => $rule->action,
                'delay_minutes' => $rule->delay_minutes,
                'settings' => $rule->settings,
                'is_enabled' => $rule->is_enabled,
            ]);

            return $rule->refresh();
        });
    }
}
