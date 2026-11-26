<?php

namespace App\Console\Commands;

use App\Services\Automations\Commands\DispatchDueMessagesCommand;
use Illuminate\Console\Command;

class DispatchDueMessages extends Command
{
    protected $signature = 'automations:dispatch-due-messages {--limit=100}';

    protected $description = 'Claim and queue due customer messages';

    public function handle(DispatchDueMessagesCommand $command): int
    {
        $count = $command->execute((int) $this->option('limit'));
        $this->info("Dispatched {$count} scheduled messages.");

        return self::SUCCESS;
    }
}
