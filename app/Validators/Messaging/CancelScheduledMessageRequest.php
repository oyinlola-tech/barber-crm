<?php

namespace App\Validators\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class CancelScheduledMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->attributes->get('currentBusiness');

        return $business !== null && $this->user()?->hasBusinessPermission((int) $business->getKey(), 'messages.cancel') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
