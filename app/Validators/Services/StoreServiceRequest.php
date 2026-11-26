<?php

namespace App\Validators\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        $permission = $this->isMethod('post') ? 'services.create' : 'services.update';

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, $permission) ?? false);
    }

    public function rules(): array
    {
        $businessId = (int) $this->attributes->get('currentBusiness')?->getKey();

        return [
            'service_category_id' => ['nullable', 'integer', Rule::exists('service_categories', 'id')->where('business_id', $businessId)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
