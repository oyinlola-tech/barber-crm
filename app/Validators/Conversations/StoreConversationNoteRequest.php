<?php

namespace App\Validators\Conversations;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = (int) $this->attributes->get('currentBusiness')->getKey();

        return $this->user()?->hasBusinessPermission($businessId, 'conversations.reply') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:1', 'max:4000']];
    }
}
