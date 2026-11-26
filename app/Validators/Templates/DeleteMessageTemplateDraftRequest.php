<?php

namespace App\Validators\Templates;

use Illuminate\Foundation\Http\FormRequest;

class DeleteMessageTemplateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, 'templates.delete') ?? false);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
