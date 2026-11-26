<?php

namespace App\Interfaces\Repositories\Conversations;

use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ConversationRepositoryInterface
{
    public function paginateForBusiness(int $businessId, ?string $status = null, ?string $search = null): LengthAwarePaginator;

    public function findForBusiness(int $businessId, int $conversationId, bool $includeInternalNotes = true): Conversation;

    public function latestInboundMessageIdForCustomer(int $businessId, int $customerId, string $channel): ?int;
}
