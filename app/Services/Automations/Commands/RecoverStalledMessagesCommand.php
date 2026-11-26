<?php

namespace App\Services\Automations\Commands;

use App\Enums\MessageStatus;
use App\Enums\ScheduledMessageStatus;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\ScheduledMessage;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;

class RecoverStalledMessagesCommand
{
    public function __construct(private readonly RecordAuditLogCommand $audit) {}

    /** @return array{requeued: int, reconciliation_required: int, manual_delivery_uncertain: int} */
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

            $manualSending = Message::query()
                ->where('direction', 'outbound')
                ->where('type', 'template')
                ->where('status', MessageStatus::Pending->value)
                ->whereNotNull('idempotency_key')
                ->where('created_at', '<', $cutoff)
                ->lockForUpdate()
                ->get();

            foreach ($manualSending as $message) {
                $message->forceFill([
                    'status' => MessageStatus::DeliveryUncertain->value,
                    'failed_at' => now(),
                    'provider_error_code' => 'manual_template_dispatch_stalled',
                ])->save();
                MessageEvent::query()->create([
                    'business_id' => $message->business_id,
                    'message_id' => $message->getKey(),
                    'event_type' => 'message.send_uncertain',
                    'status' => MessageStatus::DeliveryUncertain->value,
                    'occurred_at' => now(),
                ]);
                $this->audit->execute(
                    (int) $message->business_id,
                    null,
                    'message.manual_template_reconciliation_required',
                    Message::class,
                    (int) $message->getKey(),
                    ['status' => MessageStatus::Pending->value],
                    ['status' => MessageStatus::DeliveryUncertain->value],
                );
            }

            return [
                'requeued' => $stalledProcessing,
                'reconciliation_required' => $stalledSending,
                'manual_delivery_uncertain' => $manualSending->count(),
            ];
        });
    }
}
