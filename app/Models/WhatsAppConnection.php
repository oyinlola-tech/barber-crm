<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'phone_number_id', 'business_account_id', 'access_token', 'status', 'connected_at', 'metadata'])]
class WhatsAppConnection extends Model
{
    protected $table = 'whatsapp_connections';

    protected $hidden = ['access_token'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    protected function casts(): array
    {
        return ['access_token' => 'encrypted',
            'metadata' => 'array',
            'connected_at' => 'datetime'];
    }
}
