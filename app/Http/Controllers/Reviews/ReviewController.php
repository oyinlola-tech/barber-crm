<?php

namespace App\Http\Controllers\Reviews;

use App\Http\Controllers\Controller;
use App\Services\Customers\Queries\ListCustomersQuery;
use App\Services\Reviews\Commands\RecordReviewCommand;
use App\Services\Reviews\Queries\ListReviewsQuery;
use App\Validators\Reviews\StoreReviewRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request, ListReviewsQuery $query, ListCustomersQuery $customers): Response
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless($request->user()->hasBusinessPermission($businessId, 'reviews.view'), 403);

        return Inertia::render('reviews/index', [
            'reviews' => $query->execute($businessId),
            'customers' => $customers->execute($businessId),
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
}
