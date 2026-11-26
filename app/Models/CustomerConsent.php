<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'customer_id', 'channel', 'granted', 'source', 'recorded_at', 'revoked_at'])]
class CustomerConsent extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function casts(): array
    {
        return ['granted' => 'boolean',
            'recorded_at' => 'datetime',
            'revoked_at' => 'datetime'];
    }
}
