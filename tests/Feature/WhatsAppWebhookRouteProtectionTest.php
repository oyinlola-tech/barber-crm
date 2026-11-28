<?php

use Illuminate\Support\Facades\Route;

it('rate limits the public verification handshake as well as webhook delivery', function (): void {
    $verificationRoute = Route::getRoutes()->getByName('webhooks.whatsapp.verify');
    $deliveryRoute = Route::getRoutes()->getByName('webhooks.whatsapp.receive');

    expect($verificationRoute)->not->toBeNull()
        ->and($verificationRoute->gatherMiddleware())->toContain('throttle:60,1')
        ->and($deliveryRoute)->not->toBeNull()
        ->and($deliveryRoute->gatherMiddleware())->toContain('throttle:60,1');
});
