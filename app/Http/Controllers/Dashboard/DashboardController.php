<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Analytics\Queries\GetDashboardAnalyticsQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GetDashboardAnalyticsQuery $analytics): Response
    {
        $business = $request->attributes->get('currentBusiness');

        return Inertia::render('dashboard/index', [
            'business' => [
                'name' => $business->name,
            ],
            'analytics' => $analytics->execute($business->getKey()),
            'canViewOperations' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'system.health.view'),
            'canViewAudit' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'audit.view'),
        ]);
    }
}
