<?php

namespace App\Services\Audit\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ListAuditLogsQuery
{
    /** @return LengthAwarePaginator<int, array{id:int,action:string,resource:?string,resource_id:?int,actor:?string,ip_address:?string,created_at:string}> */
    public function execute(int $businessId, ?string $action = null): LengthAwarePaginator
    {
        $logs = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('audit_logs.business_id', $businessId)
            ->when($action !== null && $action !== '', fn ($query) => $query->where('audit_logs.action', $action))
            ->orderByDesc('audit_logs.created_at')
            ->orderByDesc('audit_logs.id')
            ->select([
                'audit_logs.id', 'audit_logs.action', 'audit_logs.auditable_type',
                'audit_logs.auditable_id', 'audit_logs.ip_address', 'audit_logs.created_at',
                'users.name as actor',
            ])
            ->paginate(50)
            ->withQueryString();

        $logs->getCollection()->transform(static fn (object $log): array => [
            'id' => (int) $log->id,
            'action' => (string) $log->action,
            'resource' => $log->auditable_type === null ? null : class_basename((string) $log->auditable_type),
            'resource_id' => $log->auditable_id === null ? null : (int) $log->auditable_id,
            'actor' => $log->actor === null ? null : (string) $log->actor,
            'ip_address' => $log->ip_address === null ? null : (string) $log->ip_address,
            'created_at' => (string) $log->created_at,
        ]);

        return $logs;
    }
}
