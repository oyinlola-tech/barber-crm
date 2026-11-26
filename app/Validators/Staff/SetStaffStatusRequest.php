<?php

namespace App\Validators\Staff;

use Illuminate\Foundation\Http\FormRequest;

class SetStaffStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();
        $permission = $this->string('status')->value() === 'active' ? 'staff.activate' : 'staff.suspend';

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, $permission) ?? false);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', 'in:active,suspended']];
    }
}
