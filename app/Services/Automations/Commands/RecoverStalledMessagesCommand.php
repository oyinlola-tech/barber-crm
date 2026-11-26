<?php

namespace App\Services\Automations\Commands;

use App\Enums\ScheduledMessageStatus;
use App\Models\ScheduledMessage;
use Illuminate\Support\Facades\DB;

class RecoverStalledMessagesCommand
{
    /** @return array{requeued: int, reconciliation_required: int} */
    public function execute(): array
    {
        return DB::transaction(function (): array {
            $cutoff = now()->subMinutes((int) config('automation.stalled_after_minutes'));
            $stalledProcessing = ScheduledMessage::query()
                ->where('status', ScheduledMessageStatus::Processing->value)
                ->where('claimed_at', '<', $cutoff)
                ->update([
                    'status' => ScheduledMessageStatus::Pending->value,
                    'scheduled_at' => now(),
                    'claimed_at' => null,
                    'last_error' => 'Stale pre-send claim safely returned to the due queue.',
                    'updated_at' => now(),
                ]);

            $stalledSending = ScheduledMessage::query()
                ->where('status', ScheduledMessageStatus::Sending->value)
                ->where('claimed_at', '<', $cutoff)
                ->update([
                    'status' => ScheduledMessageStatus::ReconciliationRequired->value,
                    'last_error' => 'Provider dispatch stalled; delivery outcome requires reconciliation before retry.',
                    'updated_at' => now(),
                ]);

            return ['requeued' => $stalledProcessing, 'reconciliation_required' => $stalledSending];
        });
    }
}
