<?php

namespace App\Jobs\Operations;

use App\Services\Operations\Commands\RecordOperationalHeartbeatCommand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordQueueWorkerHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(RecordOperationalHeartbeatCommand $command): void
    {
        $command->execute('queue_worker');
    }
}
