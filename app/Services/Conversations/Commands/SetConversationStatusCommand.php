<?php

namespace App\Services\Conversations\Commands;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class SetConversationStatusCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $conversationId, int $actorId, string $status): Conversation
    {
        $actor = User::query()->findOrFail($actorId);
        abort_unless($actor->hasBusinessPermission($businessId, 'conversations.close'), 403);
        abort_unless(in_array($status, ['open', 'closed'], true), 422);

        return DB::transaction(function () use ($businessId, $conversationId, $actorId, $status): Conversation {
            $conversation = Conversation::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($conversationId);
            $previousStatus = $conversation->status;
            $conversation->update(['status' => $status]);
            $this->audit->execute($businessId, $actorId, 'conversation.status_changed', Conversation::class, $conversationId, ['status' => $previousStatus], ['status' => $status]);

            return $conversation->refresh();
        });
    }
}
