<?php

namespace App\Services\Operations\Commands;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordOperationalHeartbeatCommand
{
    public function execute(string $component): void
    {
        if (! in_array($component, ['scheduler', 'queue_worker'], true)) {
            throw new InvalidArgumentException('Unsupported operational heartbeat component.');
        }

        DB::table('operational_heartbeats')->updateOrInsert(
            ['component' => $component],
            ['last_seen_at' => now(), 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
