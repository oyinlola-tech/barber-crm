<?php

namespace App\Http\Controllers\Customers;

use App\Enums\CustomerReturnIntentionStatus;
use App\Http\Controllers\Controller;
use App\Services\Customers\Commands\UpdateReturnIntentionStatusCommand;
use App\Validators\Customers\UpdateReturnIntentionStatusRequest;
use Illuminate\Http\RedirectResponse;

class CustomerReturnIntentionController extends Controller
{
    public function update(UpdateReturnIntentionStatusRequest $request, int $customer, UpdateReturnIntentionStatusCommand $command): RedirectResponse
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        $updated = $command->execute(
            $businessId,
            $customer,
            $request->user(),
            CustomerReturnIntentionStatus::from($request->validated('status')),
        );

        return to_route('customers.show', $updated->getKey())->with('success', 'Customer return status updated.');
    }
}
