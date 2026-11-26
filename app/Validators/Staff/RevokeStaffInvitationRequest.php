<?php

namespace App\Validators\Staff;

use Illuminate\Foundation\Http\FormRequest;

class RevokeStaffInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, 'staff.invite') ?? false);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
