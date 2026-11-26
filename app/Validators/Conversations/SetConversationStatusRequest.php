<?php

namespace App\Validators\Conversations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetConversationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = (int) $this->attributes->get('currentBusiness')->getKey();

        return $this->user()?->hasBusinessPermission($businessId, 'conversations.close') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['open', 'closed'])]];
    }
}
