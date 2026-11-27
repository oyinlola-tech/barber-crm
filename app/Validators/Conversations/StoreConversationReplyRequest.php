<?php

namespace App\Validators\Conversations;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = (int) $this->attributes->get('currentBusiness')->getKey();

        return $this->user()?->hasBusinessPermission($businessId, 'conversations.reply') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:4096'],
            'idempotency_key' => ['required', 'uuid'],
            'buttons' => ['sometimes', 'array', 'min:1', 'max:3'],
            'buttons.*' => ['required', 'string', 'min:1', 'max:20', 'distinct'],
        ];
    }
}
