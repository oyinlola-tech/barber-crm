<?php

namespace App\Validators\Customers;

use Illuminate\Foundation\Http\FormRequest;

class ImportCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = (int) $this->attributes->get('currentBusiness')?->getKey();

        return $this->user()?->hasBusinessPermission($businessId, 'customers.import') ?? false;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']];
    }
}
