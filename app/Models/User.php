<?php

namespace App\Models;

use App\Notifications\BrandedPasswordResetNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** @param string $token */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new BrandedPasswordResetNotification($token));
    }

    /** @return BelongsToMany<Business, $this, BusinessMember> */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_members')
            ->using(BusinessMember::class)
            ->withPivot('role', 'status', 'joined_at')
            ->withTimestamps();
    }

    public function hasBusinessPermission(int $businessId, string $permission): bool
    {
        $membership = $this->businesses()
            ->whereKey($businessId)
            ->wherePivot('status', 'active')
            ->first();

        if ($membership === null) {
            return false;
        }

        $permissions = $membership->pivot->role === 'owner'
            ? ['*']
            : (Role::query()->where('slug', $membership->pivot->role)
                ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $businessId))
                ->orderByRaw('business_id is null asc')
                ->value('permissions') ?? []);

        return in_array('*', $permissions, true)
            || in_array($permission, $permissions, true)
            || in_array(strstr($permission, '.', true).'.*', $permissions, true);
    }

    /** @return list<string> */
    public function businessPermissions(int $businessId): array
    {
        $membership = $this->businesses()
            ->whereKey($businessId)
            ->wherePivot('status', 'active')
            ->first();

        if ($membership === null) {
            return [];
        }

        if ($membership->pivot->role === 'owner') {
            return ['*'];
        }

        $permissions = Role::query()
            ->where('slug', $membership->pivot->role)
            ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $businessId))
            ->orderByRaw('business_id is null asc')
            ->value('permissions');

        return is_array($permissions) ? array_values($permissions) : [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
