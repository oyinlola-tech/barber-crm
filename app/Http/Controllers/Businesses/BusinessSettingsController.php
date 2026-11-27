<?php

namespace App\Http\Controllers\Businesses;

use App\Http\Controllers\Controller;
use App\Services\Businesses\Commands\UpdateBusinessCommand;
use App\Services\Settings\Queries\GetWhatsAppMessagingSettingsQuery;
use App\Validators\Businesses\UpdateBusinessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessSettingsController extends Controller
{
    public function show(Request $request, GetWhatsAppMessagingSettingsQuery $messagingSettings): Response
    {
        $business = $request->attributes->get('currentBusiness');
        abort_unless($request->user()->hasBusinessPermission((int) $business->getKey(), 'settings.view'), 403);

        return Inertia::render('settings/business', [
            'business' => [
                ...$business->only([
                    'id', 'name', 'slug', 'timezone', 'currency', 'phone', 'email',
                    'description', 'address', 'public_review_url',
                ]),
                'logo_url' => is_string($business->logo_path)
                    ? route('settings.business.logo')
                    : null,
            ],
            'canUpdate' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'settings.update'),
            'optOutKeywords' => $messagingSettings->additionalPhrases((int) $business->getKey()),
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

    public function showLogo(Request $request): StreamedResponse
    {
        $business = $request->attributes->get('currentBusiness');
        abort_unless($request->user()->hasBusinessPermission((int) $business->getKey(), 'settings.view'), 403);

        $path = $business->logo_path;
        abort_unless(
            is_string($path)
                && dirname($path) === "businesses/{$business->getKey()}/logo"
                && basename($path) !== '.'
                && basename($path) !== '..',
            404,
        );
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }
}
