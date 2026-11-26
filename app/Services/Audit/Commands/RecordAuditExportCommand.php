<?php

namespace App\Services\Audit\Commands;

use App\Models\User;

class RecordAuditExportCommand
{
    public function __construct(private readonly RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $actorId): void
    {
        $actor = User::query()->findOrFail($actorId);
        abort_unless($actor->hasBusinessPermission($businessId, 'audit.export'), 403);
        $this->audit->execute($businessId, $actorId, 'audit.exported', null, null, null, ['format' => 'csv']);
    }
}
