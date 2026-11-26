<?php

namespace App\Validators\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = Customer::query()
            ->where('business_id', $this->attributes->get('currentBusiness')?->getKey())
            ->find($this->route('customer'));

        if ($customer === null || $this->user() === null) {
            return false;
        }

        $permission = $this->boolean('granted') ? 'consent.record' : 'consent.revoke';

        return $this->user()->hasBusinessPermission((int) $customer->business_id, $permission);
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'in:whatsapp,email,sms'],
            'granted' => ['required', 'boolean'],
            'source' => ['nullable', 'string', 'max:120'],
        ];
    }
}
