<?php

namespace App\Http\Controllers\WhatsApp;

use App\Configs\WhatsAppConfig;
use App\Http\Controllers\Controller;
use App\Services\WhatsApp\Commands\ConnectWhatsAppCommand;
use App\Services\WhatsApp\Commands\DisconnectWhatsAppCommand;
use App\Services\WhatsApp\Queries\GetWhatsAppConnectionQuery;
use App\Validators\WhatsApp\ConnectWhatsAppRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppConnectionController extends Controller
{
    public function show(Request $request, GetWhatsAppConnectionQuery $query, WhatsAppConfig $configuration): Response
    {
        $businessId = $request->attributes->get('currentBusiness')->getKey();
        abort_unless($request->user()->hasBusinessPermission((int) $businessId, 'whatsapp.view'), 403);
        $connection = $query->execute($businessId);

        return Inertia::render('whatsapp/connection', [
            'connection' => $connection?->only(['id', 'phone_number_id', 'business_account_id', 'status', 'connected_at']),
            'canConfigure' => $request->user()->hasBusinessPermission((int) $businessId, 'whatsapp.configure'),
            'canDisconnect' => $request->user()->hasBusinessPermission((int) $businessId, 'whatsapp.disconnect'),
            'apiVersionConfigured' => $configuration->graphApiConfigured(),
        ]);
    }

    public function store(ConnectWhatsAppRequest $request, ConnectWhatsAppCommand $command): RedirectResponse
    {
        $command->execute($request->attributes->get('currentBusiness')->getKey(), $request->user()->getKey(), $request->toDto());

        return back()->with('success', 'WhatsApp connection saved.');
    }

    public function destroy(Request $request, DisconnectWhatsAppCommand $command): RedirectResponse
    {
        $businessId = $request->attributes->get('currentBusiness')->getKey();
        abort_unless($request->user()->hasBusinessPermission((int) $businessId, 'whatsapp.disconnect'), 403);
        $command->execute($businessId, $request->user()->getKey());

        return back()->with('success', 'WhatsApp disconnected.');
    }
}
