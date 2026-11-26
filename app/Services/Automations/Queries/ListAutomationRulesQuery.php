<?php

namespace App\Services\Automations\Queries;

use App\Models\AutomationRule;
use Illuminate\Database\Eloquent\Collection;

class ListAutomationRulesQuery
{
    /** @return Collection<int, AutomationRule> */
    public function execute(int $businessId): Collection
    {
        return AutomationRule::query()->where('business_id', $businessId)->orderBy('name')->get();
    }
}
