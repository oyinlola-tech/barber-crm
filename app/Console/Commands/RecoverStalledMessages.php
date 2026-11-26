<?php

namespace App\Console\Commands;

use App\Services\Automations\Commands\RecoverStalledMessagesCommand;
use Illuminate\Console\Command;

class RecoverStalledMessages extends Command
{
    protected $signature = 'automations:recover-stalled-messages';

    protected $description = 'Move stalled message claims to reconciliation for operator review';

    public function handle(RecoverStalledMessagesCommand $command): int
    {
        $result = $command->execute();
        $this->info("Requeued {$result['requeued']} safe pre-send claims; marked {$result['reconciliation_required']} ambiguous sends for reconciliation.");

        return self::SUCCESS;
    }
}
