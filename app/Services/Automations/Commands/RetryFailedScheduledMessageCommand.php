<?php

namespace App\Services\Automations\Commands;

use App\Enums\ScheduledMessageStatus;
use App\Jobs\Automations\ProcessScheduledMessageJob;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Repositories\Automations\ScheduledMessageRepository;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetryFailedScheduledMessageCommand
{
    public function __construct(
        private readonly RecordAuditLogCommand $audit,
        private readonly ScheduledMessageRepository $messages,
    ) {}

    public function execute(int $businessId, int $scheduledMessageId, int $actorId): ScheduledMessage
    {
        return DB::transaction(function () use ($businessId, $scheduledMessageId, $actorId): ScheduledMessage {
            $actor = User::query()->findOrFail($actorId);
            abort_unless($actor->hasBusinessPermission($businessId, 'messages.retry'), 403);

            $message = $this->messages->lockForBusiness($businessId, $scheduledMessageId);
            if ($message->status !== ScheduledMessageStatus::Failed->value) {
                throw ValidationException::withMessages(['message' => 'Only definitively failed messages can be retried.']);
            }

            $retryCount = (int) (($message->payload ?? [])['manual_retry_count'] ?? 0);
            $retryLimit = min(5, max(0, (int) config('automation.manual_retry_limit', 2)));
            if ($retryCount >= $retryLimit) {
                throw ValidationException::withMessages(['message' => 'This message has reached its manual retry limit.']);
            }

            $payload = $message->payload ?? [];
            $payload['manual_retry_count'] = $retryCount + 1;
            $previousStatus = $message->status;
            $message->update([
                'status' => ScheduledMessageStatus::Queued->value,
                'payload' => $payload,
                'scheduled_at' => now(),
                'claimed_at' => now(),
                'failed_at' => null,
                'last_error' => null,
            ]);
            $this->audit->execute($businessId, $actorId, 'scheduled_message.retry_queued', ScheduledMessage::class, $scheduledMessageId,
                ['status' => $previousStatus], ['status' => ScheduledMessageStatus::Queued->value, 'manual_retry_count' => $retryCount + 1]);
            ProcessScheduledMessageJob::dispatch($scheduledMessageId)->afterCommit();

            return $message->refresh();
        });
    }
}
