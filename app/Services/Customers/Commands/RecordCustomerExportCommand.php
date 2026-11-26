<?php

namespace App\Services\Customers\Commands;

use App\Services\Audit\Commands\RecordAuditLogCommand;

class RecordCustomerExportCommand
{
    public function __construct(private readonly RecordAuditLogCommand $audit) {}

    public function execute(int $businessId, int $userId): void
    {
        $this->audit->execute($businessId, $userId, 'customers.exported', null, null, null, ['format' => 'csv']);
    }
}
