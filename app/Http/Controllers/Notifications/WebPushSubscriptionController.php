<?php

namespace App\Http\Controllers\Notifications;

use App\Configs\WebPushConfig;
use App\Http\Controllers\Controller;
use App\Services\Notifications\Commands\RemoveWebPushSubscriptionCommand;
use App\Services\Notifications\Commands\SaveWebPushSubscriptionCommand;
use App\Validators\Notifications\StoreWebPushSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebPushSubscriptionController extends Controller
{
    public function store(
        StoreWebPushSubscriptionRequest $request,
        SaveWebPushSubscriptionCommand $command,
        WebPushConfig $config,
    ): JsonResponse {
        abort_unless($config->isConfigured(), 503, 'Browser notifications are not configured.');

        /** @var array{endpoint:string,keys:array{p256dh:string,auth:string}} $subscription */
        $subscription = $request->validated();
        $command->execute((int) $request->user()->getKey(), $subscription);

        return response()->json(['saved' => true]);
    }

    public function destroy(Request $request, RemoveWebPushSubscriptionCommand $command): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:2048'],
        ]);

        $removed = $command->execute(
            (int) $request->user()->getKey(),
            $validated['endpoint'],
        );

        return response()->json(['removed' => $removed]);
    }
}
