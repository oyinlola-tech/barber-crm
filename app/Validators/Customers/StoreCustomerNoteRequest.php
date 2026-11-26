<?php

namespace App\Validators\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && ($this->user()?->can('addNote', $customer) ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000']];
    }
}
