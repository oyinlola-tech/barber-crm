<?php

namespace App\Services\Audit\Commands;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class RecordAuditLogCommand
{
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    public function execute(?int $businessId, ?int $userId, string $action, ?string $auditableType = null, ?int $auditableId = null, ?array $before = null, ?array $after = null): AuditLog
    {
        return AuditLog::query()->create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'before' => $before,
            'after' => $after,
            'ip_address' => app(Request::class)->ip(),
        ]);
    }
}
