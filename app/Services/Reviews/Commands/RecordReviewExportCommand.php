<?php

namespace App\Services\Reviews\Commands;

use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;

class RecordReviewExportCommand
{
    public function __construct(private readonly RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $actorId): void
    {
        $actor = User::query()->findOrFail($actorId);
        abort_unless($actor->hasBusinessPermission($businessId, 'reviews.export'), 403);
        $this->audit->execute($businessId, $actorId, 'reviews.exported', null, null, null, ['format' => 'csv']);
    }
}
