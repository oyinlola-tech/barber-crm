<?php

namespace App\Services\Automations\Queries;

use App\Models\ScheduledMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListScheduledMessagesQuery
{
    public function execute(int $businessId, ?string $status = null): LengthAwarePaginator
    {
        return ScheduledMessage::query()->where('business_id', $businessId)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->with('customer:id,first_name,last_name,phone')
            ->orderByDesc('scheduled_at')->orderByDesc('id')->paginate(50)->withQueryString();
    }
}
