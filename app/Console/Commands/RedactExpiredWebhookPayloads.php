<?php

namespace App\Console\Commands;

use App\Services\Webhooks\Commands\RedactExpiredWebhookPayloadsCommand;
use Illuminate\Console\Command;

class RedactExpiredWebhookPayloads extends Command
{
    protected $signature = 'webhooks:redact-expired-payloads';

    protected $description = 'Redact expired WhatsApp webhook payloads while retaining event IDs for deduplication';

    public function handle(RedactExpiredWebhookPayloadsCommand $redact): int
    {
        $redacted = $redact->execute();

        $this->info("Redacted {$redacted} expired WhatsApp webhook payloads.");

        return self::SUCCESS;
    }
}
