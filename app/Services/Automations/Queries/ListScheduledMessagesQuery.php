<?php

namespace App\Services\Automations\Queries;

use App\Repositories\Automations\ScheduledMessageRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListScheduledMessagesQuery
{
    public function __construct(private readonly ScheduledMessageRepository $messages) {}

    public function execute(int $businessId, ?string $status = null): LengthAwarePaginator
    {
        return $this->messages->listForBusiness($businessId, $status);
    }
}
