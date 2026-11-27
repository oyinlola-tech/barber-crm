<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property array<string, mixed> $payload */
#[Fillable(['business_id', 'provider', 'event_id', 'event_type', 'payload', 'status', 'processed_at'])]
class WebhookEvent extends Model
{
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return HasMany<MessageEvent, $this> */
    public function messageEvents(): HasMany
    {
        return $this->hasMany(MessageEvent::class);
    }

    protected function casts(): array
    {
        return ['payload' => 'array',
            'processed_at' => 'datetime'];
    }
}
