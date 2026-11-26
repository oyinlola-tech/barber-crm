<?php

namespace App\Validators\WhatsApp;

use App\Dtos\WhatsApp\ConnectWhatsAppDto;
use Illuminate\Foundation\Http\FormRequest;

class ConnectWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, 'whatsapp.configure') ?? false);
    }

    public function rules(): array
    {
        return [
            'phone_number_id' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:128'],
            'business_account_id' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:128'],
            'access_token' => ['required', 'string', 'max:8192'],
        ];
    }

    public function toDto(): ConnectWhatsAppDto
    {
        $validated = $this->validated();

        return new ConnectWhatsAppDto(
            $validated['phone_number_id'],
            $validated['business_account_id'],
            $validated['access_token'],
        );
    }
}
