<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateWebPushVapidKeys extends Command
{
    protected $signature = 'app:web-push-vapid-keys';

    protected $description = 'Generate VAPID keys for browser push notifications.';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('WEBPUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->warn('Store the private key in your deployment secret store. Do not commit or log it.');

        return self::SUCCESS;
    }
}
