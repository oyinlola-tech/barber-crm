<?php

namespace App\Services\Conversations\Queries;

use App\Interfaces\Repositories\Conversations\ConversationRepositoryInterface;
use App\Models\Conversation;

class GetConversationQuery
{
    public function __construct(public ConversationRepositoryInterface $conversations) {}

    public function execute(int $businessId, int $conversationId, bool $includeInternalNotes = true): Conversation
    {
        return $this->conversations->findForBusiness($businessId, $conversationId, $includeInternalNotes);
    }
}
