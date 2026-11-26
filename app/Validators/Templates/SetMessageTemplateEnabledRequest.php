<?php

namespace App\Validators\Templates;

use Illuminate\Foundation\Http\FormRequest;

class SetMessageTemplateEnabledRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, 'templates.update') ?? false);
    }

    public function rules(): array
    {
        return ['is_enabled' => ['required', 'boolean']];
    }
}
