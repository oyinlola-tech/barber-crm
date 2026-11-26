<?php

namespace App\Console\Commands;

use App\Services\Automations\Commands\ScheduleInactiveCustomerFollowUpsCommand;
use Illuminate\Console\Command;

class ScheduleInactiveCustomerFollowUps extends Command
{
    protected $signature = 'automations:schedule-inactive-customers {--limit=100}';

    protected $description = 'Schedule eligible re-engagement messages for inactive customers';

    public function handle(ScheduleInactiveCustomerFollowUpsCommand $command): int
    {
        $count = $command->execute((int) $this->option('limit'));
        $this->info("Scheduled {$count} inactive-customer follow-ups.");

        return self::SUCCESS;
    }
}
