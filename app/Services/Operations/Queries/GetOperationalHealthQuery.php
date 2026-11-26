<?php

namespace App\Services\Operations\Queries;

use App\Configs\WhatsAppConfig;
use App\Models\WebhookEvent;
use App\Models\WhatsAppConnection;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GetOperationalHealthQuery
{
    public function __construct(private readonly WhatsAppConfig $whatsAppConfig) {}

    /** @return array{overall:string,checked_at:string,database:array{status:string},queue:array{driver:string,status:string,pending:int,failed:int,worker_heartbeat:?string},scheduler:array{status:string,last_heartbeat:?string},whatsapp:array{status:string},webhooks:array{status:string,unprocessed:int,failed_recent:int,last_received_at:?string}} */
    public function execute(int $businessId): array
    {
        $databaseStatus = $this->databaseStatus();
        if ($databaseStatus === 'unhealthy') {
            return [
                'overall' => 'unhealthy',
                'checked_at' => now()->toIso8601String(),
                'database' => ['status' => 'unhealthy'],
                'queue' => [
                    'driver' => (string) config('queue.default'),
                    'status' => 'unknown',
                    'pending' => 0,
                    'failed' => 0,
                    'worker_heartbeat' => null,
                ],
                'scheduler' => ['status' => 'unknown', 'last_heartbeat' => null],
                'whatsapp' => ['status' => 'unknown'],
                'webhooks' => ['status' => 'unknown', 'unprocessed' => 0, 'failed_recent' => 0, 'last_received_at' => null],
            ];
        }

        $queue = $this->queueStatus();
        $scheduler = $this->heartbeatStatus('scheduler');
        $worker = $this->heartbeatStatus('queue_worker');
        if ($queue['pending'] > 0 && in_array($worker['status'], ['unknown', 'unhealthy'], true)) {
            $queue['status'] = 'unhealthy';
        } elseif ($worker['status'] === 'unhealthy') {
            $queue['status'] = 'unhealthy';
        } elseif ($worker['status'] === 'unknown' && $queue['status'] === 'healthy') {
            $queue['status'] = 'unknown';
        }
        $whatsapp = $this->whatsappStatus($businessId);
        $webhooks = $this->webhookStatus($businessId);

        $statuses = [
            $databaseStatus,
            $queue['status'],
            $scheduler['status'],
            $whatsapp['status'],
            $webhooks['status'],
        ];

        return [
            'overall' => in_array('unhealthy', $statuses, true) ? 'unhealthy'
                : (in_array('unknown', $statuses, true) || in_array('degraded', $statuses, true) ? 'degraded' : 'healthy'),
            'checked_at' => now()->toIso8601String(),
            'database' => ['status' => $databaseStatus],
            'queue' => [
                'driver' => (string) config('queue.default'),
                'status' => $queue['status'],
                'pending' => $queue['pending'],
                'failed' => $queue['failed'],
                'worker_heartbeat' => $worker['last_heartbeat'],
            ],
            'scheduler' => $scheduler,
            'whatsapp' => $whatsapp,
            'webhooks' => $webhooks,
        ];
    }

    private function databaseStatus(): string
    {
        try {
            DB::select('select 1');

            return 'healthy';
        } catch (Throwable) {
            return 'unhealthy';
        }
    }

    /** @return array{status:string,pending:int,failed:int} */
    private function queueStatus(): array
    {
        $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $driver = (string) config('queue.default');
        $databaseQueue = $driver === 'database';

        return [
            'status' => ! $databaseQueue ? 'degraded'
                : (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs') ? 'unknown'
                    : ($failed > 0 ? 'degraded' : 'healthy')),
            'pending' => $pending,
            'failed' => $failed,
        ];
    }

    /** @return array{status:string,last_heartbeat:?string} */
    private function heartbeatStatus(string $component): array
    {
        if (! Schema::hasTable('operational_heartbeats')) {
            return ['status' => 'unknown', 'last_heartbeat' => null];
        }

        $lastSeen = DB::table('operational_heartbeats')->where('component', $component)->value('last_seen_at');
        if ($lastSeen === null) {
            return ['status' => 'unknown', 'last_heartbeat' => null];
        }

        $timestamp = now()->parse((string) $lastSeen);

        return [
            'status' => $timestamp->greaterThanOrEqualTo(now()->subMinutes(3)) ? 'healthy' : 'unhealthy',
            'last_heartbeat' => $timestamp->toIso8601String(),
        ];
    }

    /** @return array{status:string} */
    private function whatsappStatus(int $businessId): array
    {
        if (! Schema::hasTable('whatsapp_connections')) {
            return ['status' => 'unknown'];
        }

        $connection = WhatsAppConnection::query()->where('business_id', $businessId)->first();
        if ($connection === null || $connection->status !== 'connected') {
            return ['status' => 'degraded'];
        }

        try {
            $encryptedAccessToken = $connection->getRawOriginal('access_token');
            $accessToken = is_string($encryptedAccessToken) ? Crypt::decryptString($encryptedAccessToken) : null;
        } catch (DecryptException) {
            return ['status' => 'degraded'];
        }

        $configured = is_string($accessToken) && $accessToken !== ''
            && is_string($connection->phone_number_id) && $connection->phone_number_id !== ''
            && is_string($connection->business_account_id) && $connection->business_account_id !== ''
            && $this->whatsAppConfig->graphApiConfigured();

        return ['status' => $configured ? 'healthy' : 'degraded'];
    }

    /** @return array{status:string,unprocessed:int,failed_recent:int,last_received_at:?string} */
    private function webhookStatus(int $businessId): array
    {
        if (! Schema::hasTable('webhook_events')) {
            return ['status' => 'unknown', 'unprocessed' => 0, 'failed_recent' => 0, 'last_received_at' => null];
        }

        $events = WebhookEvent::query()->where('business_id', $businessId);
        $unprocessed = (clone $events)->whereIn('status', ['received', 'processing'])->count();
        $failedRecent = (clone $events)->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();
        $lastReceivedAt = (clone $events)->max('created_at');
        $status = $failedRecent > 0 ? 'degraded' : ($unprocessed > 0 ? 'degraded' : 'healthy');

        return [
            'status' => $status,
            'unprocessed' => $unprocessed,
            'failed_recent' => $failedRecent,
            'last_received_at' => $lastReceivedAt === null ? null : now()->parse((string) $lastReceivedAt)->toIso8601String(),
        ];
    }
}
