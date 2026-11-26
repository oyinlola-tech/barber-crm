<?php

namespace App\Validators\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();
        $permission = $this->isMethod('post') ? 'services.categories.create' : 'services.categories.update';

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, $permission) ?? false);
    }

    public function rules(): array
    {
        $businessId = (int) $this->attributes->get('currentBusiness')?->getKey();
        $categoryId = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('service_categories', 'name')->where('business_id', $businessId)->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
