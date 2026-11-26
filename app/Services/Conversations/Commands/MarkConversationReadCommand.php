<?php

namespace App\Services\Conversations\Commands;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarkConversationReadCommand
{
    public function execute(int $businessId, int $conversationId, int $actorId): Conversation
    {
        $actor = User::query()->findOrFail($actorId);
        abort_unless($actor->hasBusinessPermission($businessId, 'conversations.view'), 403);

        return DB::transaction(function () use ($businessId, $conversationId): Conversation {
            $conversation = Conversation::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($conversationId);
            $conversation->forceFill(['unread_count' => 0])->save();

            return $conversation->refresh();
        });
    }
}
