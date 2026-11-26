<?php

namespace App\Validators\Conversations;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationTemplateReplyRequest extends FormRequest
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
            'template_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
