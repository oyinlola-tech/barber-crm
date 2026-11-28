<?php

namespace App\Services\Notifications;

use App\Configs\WebPushConfig;
use App\Interfaces\Notifications\WebPushDeliveryInterface;
use App\Models\WebPushSubscription;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

class WebPushDelivery implements WebPushDeliveryInterface
{
    public function __construct(private readonly WebPushConfig $config) {}

    /** @param array{title:string,body:string,url:string,tag:string} $payload */
    public function send(WebPushSubscription $subscription, array $payload): void
    {
        $publicKey = $this->config->publicKey();
        $privateKey = $this->config->privateKey();
        if ($publicKey === null || $privateKey === null || ! $this->config->isConfigured()) {
            return;
        }

        $endpoint = $subscription->endpoint;

        $factory = new HttpFactory;
        $client = new Client([
            'allow_redirects' => false,
            'connect_timeout' => 5,
            'timeout' => 10,
            'verify' => true,
        ]);
        $webPush = new WebPush(
            ['VAPID' => [
                'subject' => $this->config->subject(),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ]],
            ['TTL' => $this->config->ttl(), 'urgency' => 'normal'],
            $client,
            $factory,
            $factory,
        );
        $webPush->queueNotification(
            Subscription::create([
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => $subscription->public_key,
                    'auth' => $subscription->auth_token,
                ],
            ]),
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $subscription->delete();

                continue;
            }

            $status = $report->getResponse()?->getStatusCode();
            if ($status === null || $status === 429 || $status >= 500) {
                throw new RuntimeException('The browser push service returned a temporary delivery error.');
            }

            Log::warning('Web push delivery failed.', [
                'subscription_hash' => $subscription->endpoint_hash,
                'status' => $status,
            ]);
        }
    }
}
