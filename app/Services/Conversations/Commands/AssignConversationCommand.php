<?php

namespace App\Services\Conversations\Commands;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class AssignConversationCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $conversationId, int $actorId, ?int $assigneeId): Conversation
    {
        $actor = User::query()->findOrFail($actorId);
        abort_unless($actor->hasBusinessPermission($businessId, 'conversations.assign'), 403);

        return DB::transaction(function () use ($businessId, $conversationId, $actorId, $assigneeId): Conversation {
            $conversation = Conversation::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($conversationId);
            if ($assigneeId !== null) {
                abort_unless(DB::table('business_members')
                    ->where('business_id', $businessId)
                    ->where('user_id', $assigneeId)
                    ->where('status', 'active')
                    ->exists(), 422);
            }

            $previousAssigneeId = $conversation->assigned_to;
            $conversation->update(['assigned_to' => $assigneeId]);
            $this->audit->execute($businessId, $actorId, 'conversation.assigned', Conversation::class, $conversationId, ['assignee_id' => $previousAssigneeId], ['assignee_id' => $assigneeId]);

            return $conversation->refresh();
        });
    }
}
