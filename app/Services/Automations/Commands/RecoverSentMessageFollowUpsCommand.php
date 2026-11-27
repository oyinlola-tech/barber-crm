<?php

namespace App\Services\Automations\Commands;

use App\Repositories\Automations\ScheduledMessageRepository;

class RecoverSentMessageFollowUpsCommand
{
    public function __construct(
        private readonly ScheduledMessageRepository $messages,
        private readonly ScheduleNextFollowUpAfterSentMessageCommand $scheduleNextFollowUp,
    ) {}

    public function execute(int $limit = 100): int
    {
        $processed = 0;
        foreach ($this->messages->sentMessagesNeedingFollowUpReconciliation($limit) as $scheduledMessageId) {
            if ($this->scheduleNextFollowUp->execute($scheduledMessageId)) {
                $processed++;
            }
        }

        return $processed;
    }
}
