<?php

namespace App\Validators\Automations;

use App\Enums\AutomationTrigger;
use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, 'automations.create') ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'trigger' => ['required', Rule::enum(AutomationTrigger::class)],
            'action' => ['required', 'in:send_template'],
            'delay_minutes' => ['required', 'integer', 'min:0', 'max:525600'],
            'settings' => ['required', 'array:template_id,max_attempts,max_follow_ups,repeat_interval_minutes,sending_hours,inactive_after_days,reengagement_cycle_days'],
            'settings.template_id' => ['required', 'integer', 'exists:message_templates,id'],
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
            $trigger = $this->input('trigger');
            $purpose = $trigger === AutomationTrigger::CustomerInactive->value ? 'reengagement' : null;
            $settings = $this->input('settings', []);
            if (! is_array($settings) || ! isset($settings['template_id'])) {
                return;
            }

            $template = MessageTemplate::query()
                ->where('business_id', $this->attributes->get('currentBusiness')?->getKey())
                ->find($settings['template_id']);
            if ($purpose !== null && $template !== null && ! in_array($template->purpose, ['reengagement', 'retention'], true)) {
                $validator->errors()->add('settings.template_id', 'Inactive-customer rules require a re-engagement or retention template.');
            }
        }];
    }
}
