<?php

namespace App\Http\Controllers\Businesses;

use App\Http\Controllers\Controller;
use App\Services\Settings\Commands\UpdateWhatsAppMessagingSettingsCommand;
use App\Validators\Businesses\UpdateWhatsAppMessagingSettingsRequest;
use Illuminate\Http\RedirectResponse;

class MessagingSettingsController extends Controller
{
    public function update(
        UpdateWhatsAppMessagingSettingsRequest $request,
        UpdateWhatsAppMessagingSettingsCommand $command,
    ): RedirectResponse {
        $command->execute(
            (int) $request->attributes->get('currentBusiness')->getKey(),
            (int) $request->user()->getKey(),
            $request->validated('opt_out_keywords'),
            $request->validated('features'),
        );

        return back()->with('success', 'WhatsApp messaging settings updated.');
    }
}
