<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $business_id
 * @property string $first_name
 * @property string|null $last_name
 * @property string $phone
 * @property string $status
 * @property Carbon|null $archived_at
 */
#[Fillable(['business_id', 'created_by', 'first_name', 'last_name', 'phone', 'email', 'date_of_birth', 'intended_return_date', 'notes', 'status', 'archived_at'])]
class Customer extends Model
{
    use HasFactory;

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return HasMany<CustomerNote, $this> */
    public function noteEntries(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(CustomerConsent::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function scheduledMessages(): HasMany
    {
        return $this->hasMany(ScheduledMessage::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_customer_tag')->withPivot('business_id');
    }

    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    protected function casts(): array
    {
        return ['date_of_birth' => 'date',
            'intended_return_date' => 'date',
            'archived_at' => 'datetime'];
    }
}
