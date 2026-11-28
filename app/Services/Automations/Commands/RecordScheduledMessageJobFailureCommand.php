<?php

namespace App\Services\Automations\Commands;

use App\Enums\ScheduledMessageStatus;
use App\Models\ScheduledMessage;
use App\Services\Notifications\Commands\NotifyStaffOfScheduledMessageFailureCommand;
use Illuminate\Support\Facades\DB;

class RecordScheduledMessageJobFailureCommand
{
    public function __construct(private readonly NotifyStaffOfScheduledMessageFailureCommand $failureNotifier) {}

    public function execute(int $scheduledMessageId): void
    {
        $terminalFailure = DB::transaction(function () use ($scheduledMessageId): bool {
            $message = ScheduledMessage::query()->lockForUpdate()->find($scheduledMessageId);
            if ($message === null) {
                return false;
            }

            if ($message->status === ScheduledMessageStatus::Sending->value) {
                $message->update([
                    'status' => ScheduledMessageStatus::ReconciliationRequired->value,
                    'failed_at' => now(),
                    'last_error' => 'queue_job_failed_during_provider_dispatch; delivery outcome requires reconciliation',
                ]);

                return false;
            }

            if ($message->status !== ScheduledMessageStatus::Queued->value) {
                return false;
            }

            $payload = $message->payload ?? [];
            $queueAttempts = (int) ($payload['queue_attempts'] ?? 0) + 1;
            $payload['queue_attempts'] = $queueAttempts;
            $attemptLimit = min(10, max(1, (int) ($payload['max_attempts'] ?? config('automation.max_attempts'))));

            if ($queueAttempts >= $attemptLimit) {
                $message->update([
                    'status' => ScheduledMessageStatus::Failed->value,
                    'payload' => $payload,
                    'failed_at' => now(),
                    'last_error' => 'queue_processing_failed_after_bounded_attempts',
                ]);

                return true;
            }

            $backoff = array_values(array_filter(
                (array) config('automation.retry_backoff_seconds', [60, 300, 900, 3600]),
                static fn (mixed $seconds): bool => is_numeric($seconds) && (int) $seconds > 0,
            ));
            if ($backoff === []) {
                $backoff = [60];
            }
            $index = min($queueAttempts - 1, count($backoff) - 1);
            $message->update([
                'status' => ScheduledMessageStatus::Pending->value,
                'payload' => $payload,
                'scheduled_at' => now()->addSeconds((int) $backoff[$index]),
                'claimed_at' => null,
                'failed_at' => null,
                'last_error' => 'queue_processing_failed; safe to retry before provider dispatch',
            ]);

            return false;
        });

        if ($terminalFailure) {
            $message = ScheduledMessage::query()->find($scheduledMessageId);
            if ($message !== null) {
                $this->failureNotifier->execute((int) $message->business_id, $scheduledMessageId);
            }
        }
    }
}
