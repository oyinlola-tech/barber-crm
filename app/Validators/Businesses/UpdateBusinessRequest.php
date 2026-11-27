<?php

namespace App\Validators\Businesses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, 'settings.update') ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:500'],
            'public_review_url' => ['nullable', 'url:https', 'max:2048'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['NGN', 'USD', 'GBP', 'EUR'])],
        ];
    }
}
