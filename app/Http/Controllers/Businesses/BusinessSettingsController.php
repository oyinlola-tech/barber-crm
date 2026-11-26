<?php

namespace App\Http\Controllers\Businesses;

use App\Http\Controllers\Controller;
use App\Services\Businesses\Commands\UpdateBusinessCommand;
use App\Validators\Businesses\UpdateBusinessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $business = $request->attributes->get('currentBusiness');
        abort_unless($request->user()->hasBusinessPermission((int) $business->getKey(), 'settings.view'), 403);

        return Inertia::render('settings/business', [
            'business' => $business->only(['id', 'name', 'slug', 'timezone', 'currency', 'phone', 'email']),
            'canUpdate' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'settings.update'),
        ]);
    }

    public function update(UpdateBusinessRequest $request, UpdateBusinessCommand $command): RedirectResponse
    {
        $command->execute(
            $request->attributes->get('currentBusiness')->getKey(),
            $request->user()->getKey(),
            $request->validated(),
        );

        return back()->with('success', 'Business settings updated.');
    }
}
