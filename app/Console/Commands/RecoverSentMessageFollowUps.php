<?php

namespace App\Console\Commands;

use App\Services\Automations\Commands\RecoverSentMessageFollowUpsCommand;
use Illuminate\Console\Command;

class RecoverSentMessageFollowUps extends Command
{
    protected $signature = 'automations:recover-sent-follow-ups {--limit=100}';

    protected $description = 'Recover follow-up scheduling after sent messages';

    public function handle(RecoverSentMessageFollowUpsCommand $command): int
    {
        $processed = $command->execute((int) $this->option('limit'));
        $this->info("Reconciled {$processed} sent-message follow-up workflows.");

        return self::SUCCESS;
    }
}
