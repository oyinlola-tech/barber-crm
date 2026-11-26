<?php

namespace App\Services\Audit\Queries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ExportAuditLogsQuery
{
    /** @return LazyCollection<int, array{action:string,resource:string,resource_id:string,actor:string,ip_address:string,created_at:string}> */
    public function execute(int $businessId, ?string $action = null): LazyCollection
    {
        return DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('audit_logs.business_id', $businessId)
            ->when($action !== null && $action !== '', fn ($query) => $query->where('audit_logs.action', $action))
            ->orderBy('audit_logs.id')
            ->select([
                'audit_logs.action', 'audit_logs.auditable_type', 'audit_logs.auditable_id',
                'audit_logs.ip_address', 'audit_logs.created_at', 'users.name as actor',
            ])
            ->cursor()
            ->map(static fn (object $log): array => [
                'action' => (string) $log->action,
                'resource' => $log->auditable_type === null ? '' : class_basename((string) $log->auditable_type),
                'resource_id' => $log->auditable_id === null ? '' : (string) $log->auditable_id,
                'actor' => $log->actor === null ? 'System' : (string) $log->actor,
                'ip_address' => $log->ip_address === null ? '' : (string) $log->ip_address,
                'created_at' => (string) $log->created_at,
            ]);
    }
}
