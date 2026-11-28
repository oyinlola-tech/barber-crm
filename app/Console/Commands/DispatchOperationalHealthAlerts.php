<?php

namespace App\Console\Commands;

use App\Services\Notifications\Commands\DispatchOperationalHealthAlertsCommand;
use Illuminate\Console\Command;

class DispatchOperationalHealthAlerts extends Command
{
    protected $signature = 'operations:dispatch-health-alerts';

    protected $description = 'Notify authorized staff when system health degrades or recovers.';

    public function handle(DispatchOperationalHealthAlertsCommand $command): int
    {
        $command->execute();

        return self::SUCCESS;
    }
}
