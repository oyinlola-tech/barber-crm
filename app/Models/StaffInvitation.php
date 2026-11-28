<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $business_id
 * @property int $invited_by
 * @property string $name
 * @property string $email
 * @property string $role_slug
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 */
#[Fillable(['business_id', 'invited_by', 'name', 'email', 'role_slug', 'token_hash', 'temporary_password_hash', 'expires_at', 'accepted_at', 'revoked_at'])]
#[Hidden(['token_hash', 'temporary_password_hash'])]
class StaffInvitation extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
