<?php

namespace App\Validators\Customers;

use App\Enums\CustomerReturnIntentionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturnIntentionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = (int) $this->attributes->get('currentBusiness')?->getKey();
        $permission = $this->input('status') === CustomerReturnIntentionStatus::Arrived->value
            ? 'visits.create'
            : 'visits.update';

        return $businessId > 0 && ($this->user()?->hasBusinessPermission($businessId, $permission) ?? false);
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([
            CustomerReturnIntentionStatus::Arrived->value,
            CustomerReturnIntentionStatus::Missed->value,
        ])]];
    }
}
