<?php

namespace App\Validators\Customers;

use App\Dtos\Customers\CreateCustomerDto;
use App\Models\Customer;
use App\Utils\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    public function rules(): array
    {
        $businessId = (int) $this->attributes->get('currentBusiness')?->getKey();
        $canAddNotes = $this->user()?->hasBusinessPermission($businessId, 'customers.note') ?? false;

        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32', app(ValidPhoneNumber::class), Rule::unique('customers', 'phone')->where('business_id', $businessId)],
            'email' => ['nullable', 'email', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'intended_return_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => $canAddNotes ? ['nullable', 'string', 'max:5000'] : ['prohibited'],
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

    public function toDto(): CreateCustomerDto
    {
        $validated = $this->validated();

        return new CreateCustomerDto(
            $validated['first_name'],
            $validated['last_name'] ?? null,
            $validated['phone'],
            $validated['email'] ?? null,
            $validated['date_of_birth'] ?? null,
            $validated['intended_return_date'] ?? null,
            $validated['notes'] ?? null,
        );
    }
}
