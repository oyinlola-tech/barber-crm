<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'message_id', 'webhook_event_id', 'provider_message_id', 'event_type', 'status', 'provider_error_code', 'occurred_at'])]
class MessageEvent extends Model
{
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class);
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
