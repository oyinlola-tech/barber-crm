<?php

namespace App\Events\Reviews;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $reviewId,
        public int $businessId,
        public int $actorId,
    ) {}
}
