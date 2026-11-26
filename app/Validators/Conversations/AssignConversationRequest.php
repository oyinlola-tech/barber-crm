<?php

namespace App\Validators\Conversations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = (int) $this->attributes->get('currentBusiness')->getKey();

        return $this->user()?->hasBusinessPermission($businessId, 'conversations.assign') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $businessId = (int) $this->attributes->get('currentBusiness')->getKey();

        return [
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('business_members', 'user_id')
                    ->where('business_id', $businessId)
                    ->where('status', 'active'),
            ],
        ];
    }
}
