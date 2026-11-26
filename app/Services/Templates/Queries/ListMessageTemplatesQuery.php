<?php

namespace App\Services\Templates\Queries;

use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Collection;

class ListMessageTemplatesQuery
{
    /** @return Collection<int, MessageTemplate> */
    public function execute(int $businessId, bool $enabledOnly = false): Collection
    {
        return MessageTemplate::query()
            ->where('business_id', $businessId)
            ->when($enabledOnly, fn ($query) => $query->where('is_enabled', true)->where('provider_status', 'APPROVED'))
            ->orderBy('name')
            ->orderBy('language')
            ->get();
    }
}
