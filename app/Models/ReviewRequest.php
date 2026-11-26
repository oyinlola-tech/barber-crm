<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['business_id', 'customer_id', 'visit_id', 'status', 'scheduled_at', 'sent_at', 'responded_at'])]
class ReviewRequest extends Model
{
    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'sent_at' => 'datetime', 'responded_at' => 'datetime'];
    }
}
