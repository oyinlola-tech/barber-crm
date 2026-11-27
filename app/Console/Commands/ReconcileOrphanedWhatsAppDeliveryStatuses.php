<?php

namespace App\Console\Commands;

use App\Services\Webhooks\Commands\ReconcileOrphanedWhatsAppDeliveryStatusesCommand;
use Illuminate\Console\Command;

class ReconcileOrphanedWhatsAppDeliveryStatuses extends Command
{
    protected $signature = 'webhooks:reconcile-delivery-statuses {--limit=200}';

    protected $description = 'Reconcile WhatsApp delivery callbacks received before their outbound message record';

    public function handle(ReconcileOrphanedWhatsAppDeliveryStatusesCommand $reconcile): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if (! is_int($limit) || $limit < 1 || $limit > 1000) {
            $this->error('The limit must be an integer between 1 and 1000.');

            return self::FAILURE;
        }

        $result = $reconcile->execute($limit);
        $this->info("Checked {$result['checked']} orphaned delivery callbacks; reconciled {$result['reconciled']}.");

        return self::SUCCESS;
    }
}
