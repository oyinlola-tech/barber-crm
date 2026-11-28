<?php

namespace App\Services\Notifications\Commands;

use App\Interfaces\Repositories\Operations\OperationalHealthAlertRepositoryInterface;
use App\Jobs\Notifications\SendOperationalHealthAlertJob;
use App\Notifications\OperationalHealthChangedNotification;
use App\Services\Operations\Queries\GetOperationalHealthQuery;
use Illuminate\Support\Facades\Cache;
use Ramsey\Uuid\Uuid;

class DispatchOperationalHealthAlertsCommand
{
    public function __construct(
        private readonly OperationalHealthAlertRepositoryInterface $repository,
        private readonly GetOperationalHealthQuery $healthQuery,
    ) {}

    public function execute(): bool
    {
        $business = $this->repository->activeBusiness();
        if ($business === null) {
            return false;
        }

        return Cache::lock('operations:health-alerts:'.$business->getKey(), 120)->block(5, function () use ($business): bool {
            $health = $this->healthQuery->execute((int) $business->getKey());
            $components = [
                'database' => $health['database']['status'],
                'queue' => $health['queue']['status'],
                'scheduler' => $health['scheduler']['status'],
                'whatsapp' => $health['whatsapp']['status'],
                'webhooks' => $health['webhooks']['status'],
            ];
            $stateKey = 'operations:health-alert-state:'.$business->getKey();
            $cachedState = Cache::get($stateKey, []);
            $previous = $cachedState['components'] ?? [];
            $revision = (int) ($cachedState['revision'] ?? 0);
            $next = $previous;
            $changes = [];

            foreach ($components as $component => $status) {
                if ($status === 'unknown') {
                    continue;
                }

                $oldStatus = $previous[$component] ?? null;
                $oldSeverity = $this->severity($oldStatus);
                $newSeverity = $this->severity($status);
                $next[$component] = $status;

                if (($oldStatus === null && $newSeverity > 0)
                    || ($oldStatus !== null && $newSeverity > $oldSeverity)
                    || ($oldSeverity > 0 && $newSeverity === 0)) {
                    $changes[] = [
                        'component' => $component,
                        'previousStatus' => $oldStatus,
                        'status' => $status,
                    ];
                }
            }

            if ($changes === []) {
                Cache::put($stateKey, ['components' => $next, 'revision' => $revision], now()->addDays(90));

                return false;
            }

            $recipients = $this->repository->alertRecipients((int) $business->getKey());
            $eventId = Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', [
                'operational-health',
                $business->getKey(),
                $revision + 1,
                json_encode($changes, JSON_THROW_ON_ERROR),
            ]))->toString();

            foreach ($recipients as $recipient) {
                if (! $recipient->notifications()->whereKey($eventId)->exists()) {
                    $recipient->notifyNow(new OperationalHealthChangedNotification(
                        $eventId,
                        (string) $business->name,
                        $changes,
                    ));
                }

                SendOperationalHealthAlertJob::dispatch(
                    (int) $recipient->getKey(),
                    (int) $business->getKey(),
                    $eventId,
                    (string) $business->name,
                    $changes,
                )->afterCommit();
            }

            Cache::put($stateKey, ['components' => $next, 'revision' => $revision + 1], now()->addDays(90));

            return true;
        });
    }

    private function severity(?string $status): int
    {
        return match ($status) {
            'unhealthy' => 2,
            'degraded' => 1,
            default => 0,
        };
    }
}
