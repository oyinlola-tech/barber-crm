<?php

namespace App\Jobs\Notifications;

use App\Services\Notifications\Commands\DeliverDailyActivityDigestCommand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDailyActivityDigestJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 172800;

    public function __construct(public readonly int $businessId, public readonly string $date) {}

    public function uniqueId(): string
    {
        return "daily-activity-digest:{$this->businessId}:{$this->date}";
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(DeliverDailyActivityDigestCommand $command): void
    {
        $command->execute($this->businessId, $this->date);
    }
}
