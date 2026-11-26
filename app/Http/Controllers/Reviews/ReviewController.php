<?php

namespace App\Http\Controllers\Reviews;

use App\Http\Controllers\Controller;
use App\Services\Customers\Queries\ListCustomersQuery;
use App\Services\Reviews\Commands\RecordReviewCommand;
use App\Services\Reviews\Commands\RecordReviewExportCommand;
use App\Services\Reviews\Commands\UpdateReviewCommand;
use App\Services\Reviews\Queries\ExportReviewsQuery;
use App\Services\Reviews\Queries\ListReviewsQuery;
use App\Utils\ReviewCsvExporter;
use App\Validators\Reviews\StoreReviewRequest;
use App\Validators\Reviews\UpdateReviewRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewController extends Controller
{
    public function index(Request $request, ListReviewsQuery $query, ListCustomersQuery $customers): Response
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless($request->user()->hasBusinessPermission($businessId, 'reviews.view'), 403);
        $canCreate = $request->user()->hasBusinessPermission($businessId, 'reviews.create')
            && $request->user()->hasBusinessPermission($businessId, 'customers.view');
        $customerOptions = $canCreate ? $customers->selectOptions($businessId) : [];

        return Inertia::render('reviews/index', [
            'reviews' => $query->execute($businessId),
            'customers' => $customerOptions,
            'permissions' => [
                'create' => $canCreate,
                'update' => $request->user()->hasBusinessPermission($businessId, 'reviews.update'),
                'export' => $request->user()->hasBusinessPermission($businessId, 'reviews.export'),
            ],
        ]);
    }

    public function store(StoreReviewRequest $request, RecordReviewCommand $command): RedirectResponse
    {
        $command->execute(
            $request->attributes->get('currentBusiness')->getKey(),
            $request->user()->getKey(),
            $request->validated(),
        );

        return to_route('reviews.index')->with('success', 'Review recorded.');
    }

    public function update(UpdateReviewRequest $request, int $review, UpdateReviewCommand $command): RedirectResponse
    {
        $command->execute(
            (int) $request->attributes->get('currentBusiness')->getKey(),
            $review,
            (int) $request->user()->getKey(),
            $request->validated(),
        );

        return back()->with('success', 'Feedback updated.');
    }

    public function export(Request $request, ExportReviewsQuery $query, ReviewCsvExporter $exporter, RecordReviewExportCommand $audit): StreamedResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless($request->user()->hasBusinessPermission($businessId, 'reviews.export'), 403);
        $audit->execute($businessId, (int) $request->user()->getKey());

        return response()->streamDownload(function () use ($businessId, $query, $exporter): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            try {
                $exporter->write($stream, $query->execute($businessId));
            } finally {
                fclose($stream);
            }
        }, 'barbershop-reviews-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
