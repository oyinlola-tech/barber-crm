<?php

namespace App\Services\Conversations\Queries;

use App\Interfaces\Repositories\Conversations\ConversationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListConversationsQuery
{
    public function __construct(public ConversationRepositoryInterface $conversations) {}

    public function execute(int $businessId, ?string $status = null, ?string $search = null): LengthAwarePaginator
    {
        return $this->conversations->paginateForBusiness($businessId, $status, $search);
    }
}
