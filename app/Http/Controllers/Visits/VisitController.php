<?php

namespace App\Http\Controllers\Visits;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Services\Visits\Commands\RecordVisitCommand;
use App\Services\Visits\Commands\RestoreVisitCommand;
use App\Services\Visits\Commands\UpdateVisitCommand;
use App\Services\Visits\Commands\VoidVisitCommand;
use App\Validators\Visits\StoreVisitRequest;
use App\Validators\Visits\UpdateVisitRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function store(StoreVisitRequest $request, RecordVisitCommand $command): RedirectResponse
    {
        $visit = $command->execute(
            $request->attributes->get('currentBusiness')->getKey(),
            $request->user(),
            $request->validated(),
        );

        return to_route('customers.show', $visit->customer_id)->with('success', 'Visit recorded.');
    }

    public function update(UpdateVisitRequest $request, Visit $visit, UpdateVisitCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless((int) $visit->business_id === $businessId, 404);
        $updatedVisit = $command->execute($businessId, $visit->getKey(), $request->user(), $request->validated());

        return to_route('customers.show', $updatedVisit->customer_id)->with('success', 'Visit corrected.');
    }

    public function destroy(Request $request, Visit $visit, VoidVisitCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless((int) $visit->business_id === $businessId, 404);
        $this->authorize('delete', $visit);
        $customerId = $visit->customer_id;
        $command->execute($businessId, $visit->getKey(), $request->user());

        return to_route('customers.show', $customerId)->with('success', 'Visit voided.');
    }

    public function restore(Request $request, Visit $visit, RestoreVisitCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless((int) $visit->business_id === $businessId, 404);
        $this->authorize('restore', $visit);
        $restoredVisit = $command->execute($businessId, $visit->getKey(), $request->user());

        return to_route('customers.show', $restoredVisit->customer_id)->with('success', 'Visit restored.');
    }
}
