<?php

namespace App\Services\Automations\Commands;

use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use Illuminate\Database\Eloquent\Builder;

class CancelCustomerReturnReminderMessagesCommand
{
    public function execute(int $businessId, int $customerId): int
    {
        $templateIds = MessageTemplate::query()
            ->where('business_id', $businessId)
            ->whereIn('purpose', config('automation.return_reminder_template_purposes', []))
            ->pluck('id');

        if ($templateIds->isEmpty()) {
            return 0;
        }

        $messages = ScheduledMessage::query()
            ->where('business_id', $businessId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['pending', 'queued'])
            ->where(function (Builder $query) use ($templateIds): void {
                foreach ($templateIds as $templateId) {
                    $query->orWhereJsonContains('payload', ['template_id' => (int) $templateId]);
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($messages as $message) {
            $message->update([
                'status' => 'cancelled',
                'claimed_at' => null,
                'last_error' => 'Cancelled because the customer provided an upcoming return date.',
            ]);
        }

        return $messages->count();
    }
}
