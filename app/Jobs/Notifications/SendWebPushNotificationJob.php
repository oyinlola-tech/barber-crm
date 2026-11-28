<?php

namespace App\Jobs\Notifications;

use App\Configs\WebPushConfig;
use App\Interfaces\Notifications\WebPushDeliveryInterface;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class SendWebPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @param array{title:string,body:string,url:string,tag:string} $payload */
    public function __construct(
        private readonly int $userId,
        private readonly string $requiredPermission,
        public readonly array $payload,
    ) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('web-push-user-'.$this->userId))->releaseAfter(10)->expireAfter(60)];
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(WebPushConfig $config, WebPushDeliveryInterface $delivery): void
    {
        if (! $config->isConfigured()) {
            return;
        }

        $user = User::query()->with('webPushSubscriptions')->find($this->userId);
        if ($user === null) {
            return;
        }

        $businessId = $user->businesses()
            ->where('businesses.is_active', true)
            ->wherePivot('status', 'active')
            ->value('businesses.id');
        if ($businessId === null || ! $user->hasBusinessPermission((int) $businessId, $this->requiredPermission)) {
            return;
        }

        foreach ($user->webPushSubscriptions as $subscription) {
            $delivery->send($subscription, $this->payload);
        }
    }
}
