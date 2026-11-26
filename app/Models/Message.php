<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $business_id
 * @property int $conversation_id
 * @property string|null $provider_message_id
 * @property string $direction
 * @property string $type
 * @property string $status
 * @property string|null $body
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 */
#[Fillable(['business_id', 'conversation_id', 'scheduled_message_id', 'provider_message_id', 'direction', 'type', 'status', 'body', 'payload', 'sent_at'])]
class Message extends Model
{
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MessageEvent::class);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
