<?php

namespace App\Services\Automations\Commands;

use App\Jobs\Automations\ProcessScheduledMessageJob;
use App\Repositories\Automations\ScheduledMessageRepository;

class DispatchDueMessagesCommand
{
    public function __construct(private ScheduledMessageRepository $messages) {}

    public function execute(int $limit = 100): int
    {
        if (! config('automation.enabled')) {
            return 0;
        }

        $ids = $this->messages->claimDue($limit);

        foreach ($ids as $id) {
            ProcessScheduledMessageJob::dispatch($id)->afterCommit();
        }

        return count($ids);
    }
}
