<?php

namespace App\Services\Customers\Commands;

use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;

class RecordCustomerExportCommand
{
    public function __construct(private readonly RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $userId): void
    {
        $actor = User::query()->findOrFail($userId);
        abort_unless($actor->hasBusinessPermission($businessId, 'customers.export'), 403);

        $this->audit->execute($businessId, $userId, 'customers.exported', null, null, null, ['format' => 'csv']);
    }
}
