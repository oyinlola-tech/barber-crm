<?php

namespace App\Validators\Customers;

use App\Utils\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('customer')) ?? false;
    }

    public function rules(): array
    {
        $businessId = (int) $this->attributes->get('currentBusiness')?->getKey();
        $customerId = $this->route('customer')?->getKey();
        $canAddNotes = $this->user()?->hasBusinessPermission($businessId, 'customers.note') ?? false;

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'phone' => ['sometimes', 'required', 'string', 'max:32', app(ValidPhoneNumber::class), Rule::unique('customers', 'phone')->where('business_id', $businessId)->ignore($customerId)],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'intended_return_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'notes' => $canAddNotes ? ['sometimes', 'nullable', 'string', 'max:5000'] : ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if (is_string($phone)) {
            $normalized = app(PhoneNumberNormalizer::class)->normalize($phone);
            if ($normalized !== null) {
                $this->merge(['phone' => $normalized]);
            }
        }
    }
}
