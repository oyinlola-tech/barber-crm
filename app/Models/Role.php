<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['business_id', 'name', 'slug', 'permissions'])]
class Role extends Model
{
    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}
