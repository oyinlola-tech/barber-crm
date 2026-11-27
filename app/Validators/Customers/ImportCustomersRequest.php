<?php

namespace App\Validators\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class ImportCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = (int) $this->attributes->get('currentBusiness')?->getKey();

        $user = $this->user();

        return $user !== null
            && $user->hasBusinessPermission($businessId, 'customers.import')
            && $user->can('viewAny', Customer::class);
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']];
    }
}
