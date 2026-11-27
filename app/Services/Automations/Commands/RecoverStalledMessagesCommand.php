<?php

namespace App\Services\Automations\Commands;

use App\Enums\MessageStatus;
use App\Enums\ScheduledMessageStatus;
use App\Models\Message;
use App\Models\MessageEvent;
use App\Models\ScheduledMessage;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use App\Services\Notifications\Commands\NotifyStaffOfUncertainMessageCommand;
use Illuminate\Support\Facades\DB;

class RecoverStalledMessagesCommand
{
    public function __construct(
        private readonly RecordAuditLogCommand $audit,
        private readonly NotifyStaffOfUncertainMessageCommand $notifications,
    ) {}

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

            $stalledSendingMessages = ScheduledMessage::query()
                ->where('status', ScheduledMessageStatus::Sending->value)
                ->where('claimed_at', '<', $cutoff)
                ->lockForUpdate()
                ->get();

            foreach ($stalledSendingMessages as $scheduledMessage) {
                $scheduledMessage->update([
                    'status' => ScheduledMessageStatus::ReconciliationRequired->value,
                    'last_error' => 'Provider dispatch stalled; delivery outcome requires reconciliation before retry.',
                ]);
                $this->notifications->execute((int) $scheduledMessage->business_id, (int) $scheduledMessage->getKey(), 'scheduled_message');
            }

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
                $this->notifications->execute((int) $message->business_id, (int) $message->getKey(), 'message');
            }

            $manualTextSending = Message::query()
                ->where('direction', 'outbound')
                ->where('type', 'text')
                ->where('status', MessageStatus::Pending->value)
                ->where('created_at', '<', $cutoff)
                ->lockForUpdate()
                ->get();

            foreach ($manualTextSending as $message) {
                $message->forceFill([
                    'status' => MessageStatus::DeliveryUncertain->value,
                    'failed_at' => now(),
                    'provider_error_code' => 'manual_text_dispatch_stalled',
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
                    'message.manual_text_reconciliation_required',
                    Message::class,
                    (int) $message->getKey(),
                    ['status' => MessageStatus::Pending->value],
                    ['status' => MessageStatus::DeliveryUncertain->value],
                );
                $this->notifications->execute((int) $message->business_id, (int) $message->getKey(), 'message');
            }

            return [
                'requeued' => $stalledProcessing,
                'reconciliation_required' => $stalledSendingMessages->count(),
                'manual_delivery_uncertain' => $manualSending->count() + $manualTextSending->count(),
            ];
        });
    }
}
