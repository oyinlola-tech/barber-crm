<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BusinessMember extends Pivot
{
    protected $table = 'business_members';

    public $incrementing = true;
}
