<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Services\Consent\Commands\RecordConsentCommand;
use App\Validators\Customers\StoreCustomerConsentRequest;
use Illuminate\Http\RedirectResponse;

class CustomerConsentController extends Controller
{
    public function store(StoreCustomerConsentRequest $request, int $customer, RecordConsentCommand $command): RedirectResponse
    {
        $command->execute(
            $request->attributes->get('currentBusiness')->getKey(),
            $customer,
            $request->validated('channel'),
            $request->boolean('granted'),
            $request->validated('source'),
            $request->user()->getKey(),
        );

        return back()->with('success', 'Consent preference recorded.');
    }
}
