<?php

namespace App\Validators\Templates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MessageTemplateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, $this->isMethod('post') ? 'templates.create' : 'templates.update') ?? false);
    }

    public function rules(): array
    {
        $allowedVariables = config('automation.allowed_template_variables', []);
        $exampleRules = [];
        foreach ($allowedVariables as $variable) {
            $exampleRules['examples.'.$variable] = ['nullable', 'string', 'max:255'];
        }

        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            'category' => ['required', Rule::in(['UTILITY', 'MARKETING'])],
            'purpose' => ['required', Rule::in(['thank_you', 'experience', 'review_request', 'return_intention', 'retention', 'reengagement', 'custom'])],
            'body' => ['required', 'string', 'max:1024'],
            'examples' => ['sometimes', 'array'],
            ...$exampleRules,
        ];
    }
}
