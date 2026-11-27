<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $business_id
 * @property int $customer_id
 * @property int|null $automation_rule_id
 * @property int|null $conversation_id
 * @property string $channel
 * @property string $idempotency_key
 * @property string $status
 * @property int $attempts
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $follow_up_reconciled_at
 */
#[Fillable(['business_id', 'customer_id', 'automation_rule_id', 'conversation_id', 'channel', 'idempotency_key', 'body', 'payload', 'status', 'scheduled_at', 'claimed_at', 'sent_at', 'follow_up_reconciled_at', 'failed_at', 'attempts', 'last_error'])]
class ScheduledMessage extends Model
{
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    protected function casts(): array
    {
        return ['payload' => 'array',
            'scheduled_at' => 'datetime',
            'claimed_at' => 'datetime',
            'sent_at' => 'datetime',
            'follow_up_reconciled_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempts' => 'integer'];
    }
}
