<?php

namespace App\Validators\Visits;

use App\Models\Visit;
use App\Utils\BusinessDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Visit::class) ?? false;
    }

    public function rules(): array
    {
        $businessId = (int) $this->attributes->get('currentBusiness')?->getKey();
        $timezone = $this->attributes->get('currentBusiness')->timezone;

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('business_id', $businessId)],
            'staff_profile_id' => ['nullable', 'integer', Rule::exists('staff_profiles', 'id')->where('business_id', $businessId)],
            'visited_at' => ['required', 'date', function (string $attribute, mixed $value, \Closure $fail) use ($timezone): void {
                if (is_string($value) && BusinessDateTime::isFuture($value, $timezone)) {
                    $fail('The visit date and time must be in the past.');
                }
            }],
            'service_ids' => ['sometimes', 'array', 'max:10'],
            'service_ids.*' => ['integer', 'distinct', Rule::exists('services', 'id')->where('business_id', $businessId)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
