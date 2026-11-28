<?php

namespace App\Validators\Automations;

use App\Enums\AutomationTrigger;
use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();
        $user = $this->user();
        if ($businessId === null || $user === null) {
            return false;
        }
        if (count($this->only(['is_enabled'])) === 1 && $this->exists('is_enabled')) {
            return $user->hasBusinessPermission((int) $businessId, $this->boolean('is_enabled') ? 'automations.enable' : 'automations.disable');
        }

        return $user->hasBusinessPermission((int) $businessId, 'automations.update');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'trigger' => ['sometimes', 'required', Rule::enum(AutomationTrigger::class)],
            'action' => ['sometimes', 'required', 'in:send_template'],
            'delay_minutes' => ['sometimes', 'required', 'integer', 'min:0', 'max:525600'],
            'settings' => ['sometimes', 'required', 'array:template_id,max_attempts,max_follow_ups,repeat_interval_minutes,sending_hours,inactive_after_days,reengagement_cycle_days'],
            'settings.template_id' => ['sometimes', 'required', 'integer', 'exists:message_templates,id'],
            'settings.max_attempts' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'settings.max_follow_ups' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'settings.repeat_interval_minutes' => ['sometimes', 'integer', 'min:0', 'max:525600'],
            'settings.inactive_after_days' => ['required_if:trigger,customer_inactive', 'integer', 'min:3', 'max:365'],
            'settings.reengagement_cycle_days' => ['sometimes', 'integer', 'min:7', 'max:365'],
            'settings.sending_hours' => ['sometimes', 'array:start,end'],
            'settings.sending_hours.start' => ['sometimes', 'date_format:H:i'],
            'settings.sending_hours.end' => ['sometimes', 'date_format:H:i'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->only(['name', 'trigger', 'action', 'delay_minutes', 'settings', 'is_enabled']) === []) {
                $validator->errors()->add('rule', 'Provide at least one rule change.');
            }

            $trigger = $this->input('trigger');
            $settings = $this->input('settings', []);
            if (! in_array($trigger, [AutomationTrigger::CustomerInactive->value, AutomationTrigger::ReturnDateMissed->value], true)
                || ! is_array($settings) || ! isset($settings['template_id'])) {
                return;
            }

            $businessId = $this->attributes->get('currentBusiness')?->getKey();
            $template = MessageTemplate::query()->where('business_id', $businessId)->find($settings['template_id']);
            $allowedPurposes = $trigger === AutomationTrigger::CustomerInactive->value
                ? ['reengagement', 'retention']
                : ['reengagement', 'retention', 'return_intention'];
            if ($template !== null && ! in_array($template->purpose, $allowedPurposes, true)) {
                $validator->errors()->add('settings.template_id', 'Choose a template whose purpose matches this follow-up rule.');
            }
        }];
    }
}
