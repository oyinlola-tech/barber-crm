<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Audit\Commands\RecordAuditExportCommand;
use App\Services\Audit\Queries\ExportAuditLogsQuery;
use App\Services\Audit\Queries\ListAuditLogsQuery;
use App\Utils\AuditLogCsvExporter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request, ListAuditLogsQuery $query): Response
    {
        $business = $request->attributes->get('currentBusiness');
        $this->authorize('viewAny', [AuditLog::class, $business]);
        $validated = $request->validate(['action' => ['nullable', 'string', 'max:100']]);

        return Inertia::render('operations/audit', [
            'logs' => $query->execute((int) $business->getKey(), $validated['action'] ?? null),
            'filters' => ['action' => $validated['action'] ?? ''],
            'canExport' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'audit.export'),
        ]);
    }

    public function export(Request $request, ExportAuditLogsQuery $query, AuditLogCsvExporter $exporter, RecordAuditExportCommand $audit): StreamedResponse
    {
        $business = $request->attributes->get('currentBusiness');
        $businessId = (int) $business->getKey();
        abort_unless($request->user()->hasBusinessPermission($businessId, 'audit.export'), 403);
        $validated = $request->validate(['action' => ['nullable', 'string', 'max:100']]);
        $audit->execute($businessId, (int) $request->user()->getKey());
        $action = $validated['action'] ?? null;

        return response()->streamDownload(function () use ($businessId, $action, $query, $exporter): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            try {
                $exporter->write($stream, $query->execute($businessId, $action));
            } finally {
                fclose($stream);
            }
        }, 'barbershop-audit-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
