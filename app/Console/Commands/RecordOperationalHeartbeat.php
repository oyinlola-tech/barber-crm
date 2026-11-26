<?php

namespace App\Console\Commands;

use App\Jobs\Operations\RecordQueueWorkerHeartbeatJob;
use App\Services\Operations\Commands\RecordOperationalHeartbeatCommand;
use Illuminate\Console\Command;

class RecordOperationalHeartbeat extends Command
{
    protected $signature = 'operations:record-heartbeat';

    protected $description = 'Record the scheduler heartbeat and enqueue a worker heartbeat probe.';

    public function handle(RecordOperationalHeartbeatCommand $command): int
    {
        $command->execute('scheduler');
        RecordQueueWorkerHeartbeatJob::dispatch();

        return self::SUCCESS;
    }
}
