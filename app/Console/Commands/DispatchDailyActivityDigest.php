<?php

namespace App\Console\Commands;

use App\Services\Notifications\Commands\DispatchDailyActivityDigestCommand;
use Illuminate\Console\Command;

class DispatchDailyActivityDigest extends Command
{
    protected $signature = 'notifications:dispatch-activity-digest';

    protected $description = 'Queue the previous business day activity digest for authorized admins.';

    public function handle(DispatchDailyActivityDigestCommand $command): int
    {
        $dispatched = $command->execute();
        $this->components->info($dispatched ? 'Activity digest queued.' : 'No activity digest was queued.');

        return self::SUCCESS;
    }
}
