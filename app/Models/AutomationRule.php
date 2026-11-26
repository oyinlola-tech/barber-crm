<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $business_id
 * @property int $delay_minutes
 * @property bool $is_enabled
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['business_id', 'name', 'trigger', 'action', 'delay_minutes', 'is_enabled', 'settings'])]
class AutomationRule extends Model
{
    public function scheduledMessages(): HasMany
    {
        return $this->hasMany(ScheduledMessage::class);
    }

    protected function casts(): array
    {
        return ['delay_minutes' => 'integer',
            'is_enabled' => 'boolean',
            'settings' => 'array'];
    }
}
