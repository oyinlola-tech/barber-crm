<?php

namespace App\Services\Automations\Commands;

use App\Enums\ScheduledMessageStatus;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Repositories\Automations\ScheduledMessageRepository;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelScheduledMessageCommand
{
    public function __construct(
        private readonly RecordAuditLogCommand $audit,
        private readonly ScheduledMessageRepository $messages,
    ) {}

    public function execute(int $businessId, int $scheduledMessageId, int $actorId): ScheduledMessage
    {
        return DB::transaction(function () use ($businessId, $scheduledMessageId, $actorId): ScheduledMessage {
            $actor = User::query()->findOrFail($actorId);
            abort_unless($actor->hasBusinessPermission($businessId, 'messages.cancel'), 403);

            $message = $this->messages->lockForBusiness($businessId, $scheduledMessageId);
            if (! in_array($message->status, [ScheduledMessageStatus::Pending->value, ScheduledMessageStatus::Queued->value], true)) {
                throw ValidationException::withMessages(['message' => 'Only pending or queued messages can be cancelled.']);
            }

            $previousStatus = $message->status;
            $message->update([
                'status' => ScheduledMessageStatus::Cancelled->value,
                'claimed_at' => null,
                'last_error' => 'Cancelled by an authorized staff member.',
            ]);
            $this->audit->execute($businessId, $actorId, 'scheduled_message.cancelled', ScheduledMessage::class, $scheduledMessageId,
                ['status' => $previousStatus], ['status' => ScheduledMessageStatus::Cancelled->value]);

            return $message->refresh();
        });
    }
}
