<?php

namespace App\Console\Commands;

use App\Services\Webhooks\Commands\RequeueStalledWhatsAppWebhookEventsCommand;
use Illuminate\Console\Command;

class RequeueStalledWhatsAppWebhookEvents extends Command
{
    protected $signature = 'webhooks:requeue-stalled {--limit=200}';

    protected $description = 'Requeue WhatsApp webhook events that were stored but not processed';

    public function handle(RequeueStalledWhatsAppWebhookEventsCommand $requeue): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if (! is_int($limit) || $limit < 1 || $limit > 1000) {
            $this->error('The limit must be an integer between 1 and 1000.');

            return self::FAILURE;
        }

        $count = $requeue->execute($limit);
        $this->info("Requeued {$count} stalled WhatsApp webhook events.");

        return self::SUCCESS;
    }
}
