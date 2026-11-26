<?php

namespace App\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Audit\Queries\ListAuditLogsQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
        ]);
    }
}
