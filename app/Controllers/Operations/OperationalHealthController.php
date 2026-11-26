<?php

namespace App\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Services\Operations\Queries\GetOperationalHealthQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class OperationalHealthController extends Controller
{
    public function show(Request $request, GetOperationalHealthQuery $query): Response
    {
        $business = $request->attributes->get('currentBusiness');
        $this->authorize('viewSystemHealth', $business);

        return Inertia::render('operations/health', [
            'health' => $query->execute((int) $business->getKey()),
        ]);
    }

    public function ready(GetOperationalHealthQuery $query): JsonResponse
    {
        try {
            $health = $query->execute(0);
            $ready = $health['database']['status'] === 'healthy';
        } catch (Throwable) {
            $ready = false;
        }

        return response()->json(
            ['status' => $ready ? 'ready' : 'unavailable'],
            $ready ? HttpResponse::HTTP_OK : HttpResponse::HTTP_SERVICE_UNAVAILABLE,
            ['Cache-Control' => 'no-store'],
        );
    }
}
