<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'timezone', 'currency', 'phone', 'email', 'settings', 'is_active'])]
/**
 * @property string $name
 * @property string $timezone
 * @property string|null $phone
 * @property array<string, mixed>|null $settings
 */
class Business extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_active' => 'boolean'];
    }

    /** @return BelongsToMany<User, $this, BusinessMember> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_members')
            ->using(BusinessMember::class)
            ->withPivot('role', 'status', 'joined_at')
            ->withTimestamps();
    }
}
