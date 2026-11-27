<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\Queries\GetAnalyticsReportQuery;
use App\Validators\Analytics\AnalyticsRangeRequest;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(AnalyticsRangeRequest $request, GetAnalyticsReportQuery $query): InertiaResponse
    {
        $business = $request->attributes->get('currentBusiness');
        $businessId = (int) $business->getKey();
        abort_unless($request->user()->hasBusinessPermission($businessId, 'analytics.view'), 403);
        [$startDate, $endDate] = $this->dateRange($request, $business->timezone);

        return Inertia::render('analytics/index', [
            'report' => $query->execute($businessId, $startDate, $endDate, $business->timezone),
            'filters' => ['start_date' => $startDate, 'end_date' => $endDate],
            'canExport' => $request->user()->hasBusinessPermission($businessId, 'analytics.export'),
        ]);
    }

    public function export(AnalyticsRangeRequest $request, GetAnalyticsReportQuery $query): StreamedResponse
    {
        $business = $request->attributes->get('currentBusiness');
        $businessId = (int) $business->getKey();
        abort_unless($request->user()->hasBusinessPermission($businessId, 'analytics.export'), 403);
        [$startDate, $endDate] = $this->dateRange($request, $business->timezone);
        $report = $query->execute($businessId, $startDate, $endDate, $business->timezone);

        return response()->streamDownload(function () use ($report, $startDate, $endDate): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fputcsv($stream, ['Analytics report', $startDate, $endDate], ',', '"', '');
            fputcsv($stream, ['Metric', 'Value'], ',', '"', '');
            foreach ($report as $metric => $value) {
                fputcsv($stream, [$metric, $value ?? ''], ',', '"', '');
            }
            fclose($stream);
        }, 'barbershop-analytics-'.$startDate.'-to-'.$endDate.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @return array{string, string} */
    private function dateRange(AnalyticsRangeRequest $request, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->toDateString();

        return [
            (string) ($request->validated('start_date') ?? CarbonImmutable::now($timezone)->subDays(29)->toDateString()),
            (string) ($request->validated('end_date') ?? $today),
        ];
    }
}
