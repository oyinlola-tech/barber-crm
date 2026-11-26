<?php

namespace App\Jobs\Automations;

use App\Services\Automations\Commands\ProcessScheduledMessageCommand;
use App\Services\Automations\Commands\RecordScheduledMessageJobFailureCommand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessScheduledMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff = [30, 120, 600, 1800];

    public int $timeout = 30;

    public function __construct(public int $scheduledMessageId)
    {
        $this->tries = 1;
    }

    public function handle(ProcessScheduledMessageCommand $command, RecordScheduledMessageJobFailureCommand $failureCommand): void
    {
        try {
            $command->execute($this->scheduledMessageId);
        } catch (\Throwable) {
            $failureCommand->execute($this->scheduledMessageId);
            throw new \RuntimeException('Scheduled WhatsApp message processing failed.');
        }
    }
}
