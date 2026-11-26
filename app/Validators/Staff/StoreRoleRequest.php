<?php

namespace App\Validators\Staff;

use App\Enums\BusinessRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();
        $permission = $this->route('role') ? 'roles.update' : 'roles.create';

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, $permission) ?? false);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'permissions' => ['present', 'array', 'max:80'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(array_keys(BusinessRole::permissions()))],
        ];
    }
}
