<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $business_id
 * @property int $customer_id
 * @property int|null $staff_profile_id
 * @property int $automation_revision
 * @property Carbon $visited_at
 * @property Carbon|null $deleted_at
 * @property-read Customer $customer
 */
#[Fillable(['business_id', 'customer_id', 'staff_profile_id', 'recorded_by', 'visited_at', 'notes'])]
class Visit extends Model
{
    use SoftDeletes;

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<StaffProfile, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }

    /** @return HasMany<VisitService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(VisitService::class);
    }

    protected function casts(): array
    {
        return ['visited_at' => 'datetime'];
    }
}
