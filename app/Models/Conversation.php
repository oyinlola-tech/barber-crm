<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $business_id
 * @property int $customer_id
 * @property int|null $assigned_to
 * @property string $channel
 * @property string $status
 * @property int $unread_count
 * @property Carbon|null $last_message_at
 * @property Carbon|null $last_customer_message_at
 * @property-read Customer $customer
 */
#[Fillable(['business_id', 'customer_id', 'channel', 'status', 'assigned_to', 'last_message_at'])]
class Conversation extends Model
{
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<ConversationNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ConversationNote::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_customer_message_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }
}
