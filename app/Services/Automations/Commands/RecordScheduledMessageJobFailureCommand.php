<?php

namespace App\Services\Automations\Commands;

use App\Enums\ScheduledMessageStatus;
use App\Models\ScheduledMessage;
use Illuminate\Support\Facades\DB;

class RecordScheduledMessageJobFailureCommand
{
    public function execute(int $scheduledMessageId): void
    {
        DB::transaction(function () use ($scheduledMessageId): void {
            $message = ScheduledMessage::query()->lockForUpdate()->find($scheduledMessageId);
            if ($message === null) {
                return;
            }

            if ($message->status === ScheduledMessageStatus::Sending->value) {
                $message->update([
                    'status' => ScheduledMessageStatus::ReconciliationRequired->value,
                    'failed_at' => now(),
                    'last_error' => 'queue_job_failed_during_provider_dispatch; delivery outcome requires reconciliation',
                ]);

                return;
            }

            if ($message->status !== ScheduledMessageStatus::Queued->value) {
                return;
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

                return;
            }

            $backoff = config('automation.retry_backoff_seconds', [60, 300, 900, 3600]);
            $index = min($queueAttempts - 1, count($backoff) - 1);
            $message->update([
                'status' => ScheduledMessageStatus::Pending->value,
                'payload' => $payload,
                'scheduled_at' => now()->addSeconds((int) $backoff[$index]),
                'claimed_at' => null,
                'last_error' => 'queue_processing_failed; safe to retry before provider dispatch',
            ]);
        });
    }
}
