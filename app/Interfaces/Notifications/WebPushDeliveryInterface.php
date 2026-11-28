<?php

namespace App\Interfaces\Notifications;

use App\Models\WebPushSubscription;

interface WebPushDeliveryInterface
{
    /** @param array{title:string,body:string,url:string,tag:string} $payload */
    public function send(WebPushSubscription $subscription, array $payload): void;
}
