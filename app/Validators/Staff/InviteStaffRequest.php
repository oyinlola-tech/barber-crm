<?php

namespace App\Validators\Staff;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return $businessId !== null && ($this->user()?->hasBusinessPermission((int) $businessId, 'staff.invite') ?? false);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $businessId = $this->attributes->get('currentBusiness')?->getKey();

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($value))])->exists()) {
                        $fail('An account already uses this email address.');
                    }
                },
            ],
            'role_slug' => ['required', 'string', Rule::exists('roles', 'slug')->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $businessId))],
        ];
    }
}
