<?php

namespace App\Validators\Templates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigureMessageTemplateVariablesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, 'templates.update') ?? false);
    }

    public function rules(): array
    {
        return [
            'variables' => ['present', 'array', 'max:10'],
            'variables.*' => ['required', 'string', Rule::in(config('automation.allowed_template_variables', []))],
        ];
    }
}
