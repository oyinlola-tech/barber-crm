<?php

namespace App\Validators\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class SyncCustomerTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && ($this->user()?->can('manageTags', $customer) ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'tags' => ['present', 'array', 'max:20'],
            'tags.*' => ['required', 'string', 'min:1', 'max:40'],
        ];
    }
}
