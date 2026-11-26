<?php

namespace App\Services\Conversations\Commands;

use App\Models\Conversation;
use App\Models\ConversationNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddConversationNoteCommand
{
    public function execute(int $businessId, int $conversationId, int $actorId, string $body): ConversationNote
    {
        $actor = User::query()->findOrFail($actorId);
        abort_unless($actor->hasBusinessPermission($businessId, 'conversations.reply'), 403);

        return DB::transaction(function () use ($businessId, $conversationId, $actorId, $body): ConversationNote {
            $conversation = Conversation::query()->where('business_id', $businessId)->findOrFail($conversationId);

            return ConversationNote::query()->create([
                'business_id' => $businessId,
                'conversation_id' => $conversation->getKey(),
                'created_by' => $actorId,
                'body' => trim($body),
            ]);
        });
    }
}
