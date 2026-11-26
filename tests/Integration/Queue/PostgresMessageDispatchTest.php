<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\ScheduledMessage;
use App\Services\Automations\Commands\RecoverStalledMessagesCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('claims due messages across concurrent PostgreSQL dispatchers and recovers stalled states', function (): void {
    if (env('CRM_POSTGRES_INTEGRATION') !== '1'
        || DB::connection()->getDriverName() !== 'pgsql'
        || DB::connection()->getDatabaseName() !== 'barber_crm_test_dispatch') {
        $this->markTestSkipped('This integration test requires its disposable PostgreSQL test database.');
    }

    if (Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }

    config(['automation.enabled' => true]);
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $firstDueAt = now()->subMinutes(3);
    $dueMessages = collect(range(1, 3))->map(fn (int $index): ScheduledMessage => ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'idempotency_key' => 'postgres-dispatch-'.$index,
        'status' => 'pending',
        'scheduled_at' => $firstDueAt->copy()->addSeconds($index),
    ]));

    $launch = function (): array {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, base_path('artisan'), 'automations:dispatch-due-messages', '--limit=1'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start a PostgreSQL dispatch worker process.');
        }

        fclose($pipes[0]);

        return [$process, $pipes[1], $pipes[2]];
    };
    $finish = function (array $worker): array {
        [$process, $stdoutPipe, $stderrPipe] = $worker;
        $stdout = stream_get_contents($stdoutPipe);
        $stderr = stream_get_contents($stderrPipe);
        fclose($stdoutPipe);
        fclose($stderrPipe);

        return ['exit' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    };

    DB::beginTransaction();
    $lockedMessage = ScheduledMessage::query()->orderBy('scheduled_at')->lockForUpdate()->firstOrFail();

    try {
        $firstWorker = $launch();
        $secondWorker = $launch();
        $firstResult = $finish($firstWorker);
        $secondResult = $finish($secondWorker);

        expect($firstResult['exit'])->toBe(0)
            ->and($secondResult['exit'])->toBe(0)
            ->and($firstResult['stderr'].$secondResult['stderr'])->toBe('')
            ->and($firstResult['stdout'].$secondResult['stdout'])->toContain('Dispatched 1 scheduled messages.')
            ->and(DB::table('jobs')->count())->toBe(2)
            ->and(ScheduledMessage::query()->whereIn('id', $dueMessages->pluck('id'))->where('status', 'queued')->count())->toBe(2)
            ->and($lockedMessage->fresh()->status)->toBe('pending');
    } finally {
        DB::rollBack();
    }

    $finalWorker = $finish($launch());
    expect($finalWorker['exit'])->toBe(0)
        ->and($finalWorker['stderr'])->toBe('')
        ->and($finalWorker['stdout'])->toContain('Dispatched 1 scheduled messages.')
        ->and(DB::table('jobs')->count())->toBe(3)
        ->and(ScheduledMessage::query()->whereIn('id', $dueMessages->pluck('id'))->where('status', 'queued')->count())->toBe(3);

    $processing = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(),
        'idempotency_key' => 'postgres-stalled-processing', 'status' => 'processing',
        'scheduled_at' => now()->subHour(), 'claimed_at' => now()->subHour(),
    ]);
    $sending = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(),
        'idempotency_key' => 'postgres-stalled-sending', 'status' => 'sending',
        'scheduled_at' => now()->subHour(), 'claimed_at' => now()->subHour(),
    ]);

    config(['automation.stalled_after_minutes' => 10]);
    expect(app(RecoverStalledMessagesCommand::class)->execute())
        ->toBe(['requeued' => 1, 'reconciliation_required' => 1, 'manual_delivery_uncertain' => 0])
        ->and($processing->fresh()->status)->toBe('pending')
        ->and($sending->fresh()->status)->toBe('reconciliation_required');
})->group('postgres');
