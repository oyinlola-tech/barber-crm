<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'visit_id', 'service_id', 'service_name', 'duration_minutes', 'price'])]
class VisitService extends Model
{
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer',
            'price' => 'decimal:2'];
    }
}
