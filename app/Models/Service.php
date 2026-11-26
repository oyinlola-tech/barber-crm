<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'service_category_id', 'name', 'description', 'duration_minutes', 'price', 'is_active'])]
class Service extends Model
{
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean'];
    }
}
