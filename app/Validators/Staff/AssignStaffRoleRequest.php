<?php

namespace App\Validators\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignStaffRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, 'roles.assign') ?? false);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return [
            'role_slug' => ['required', 'string', Rule::exists('roles', 'slug')->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $businessId))],
        ];
    }
}
