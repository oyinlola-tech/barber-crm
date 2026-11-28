<?php

use App\Interfaces\Repositories\Operations\OperationalHealthAlertRepositoryInterface;
use App\Jobs\Notifications\SendOperationalHealthAlertJob;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\OperationalHealthChangedNotification;
use App\Services\Notifications\Commands\DispatchOperationalHealthAlertsCommand;
use App\Services\Operations\Queries\GetOperationalHealthQuery;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('alerts authorized staff once on degradation, escalation, and recovery', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    Notification::fake();
    Queue::fake();

    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $receptionist = User::factory()->create();
    $suspendedOwner = User::factory()->create();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Health administrator',
        'slug' => 'health_administrator',
        'permissions' => ['system.health.view'],
    ]);
    $business->members()->attach($manager, ['role' => 'health_administrator', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($receptionist, ['role' => 'receptionist', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($suspendedOwner, ['role' => 'owner', 'status' => 'suspended', 'joined_at' => now()]);
    expect($owner->hasBusinessPermission((int) $business->getKey(), 'system.health.view'))->toBeTrue()
        ->and(app(OperationalHealthAlertRepositoryInterface::class)->alertRecipients((int) $business->getKey())->modelKeys())->toBe([$owner->getKey(), $manager->getKey()]);

    $this->mock(GetOperationalHealthQuery::class, function (MockInterface $mock): void {
        $mock->shouldReceive('execute')
            ->times(5)
            ->andReturn(
                operationalHealthSnapshot('degraded'),
                operationalHealthSnapshot('degraded'),
                operationalHealthSnapshot('unhealthy'),
                operationalHealthSnapshot('healthy'),
                operationalHealthSnapshot('healthy'),
            );
    });

    $command = app(DispatchOperationalHealthAlertsCommand::class);

    expect($command->execute())->toBeTrue()
        ->and($command->execute())->toBeFalse()
        ->and($command->execute())->toBeTrue()
        ->and($command->execute())->toBeTrue()
        ->and($command->execute())->toBeFalse();

    Notification::assertSentToTimes($owner, OperationalHealthChangedNotification::class, 3);
    Notification::assertSentToTimes($manager, OperationalHealthChangedNotification::class, 3);
    Notification::assertNotSentTo($receptionist, OperationalHealthChangedNotification::class);
    Notification::assertNotSentTo($suspendedOwner, OperationalHealthChangedNotification::class);
    Queue::assertPushedTimes(SendOperationalHealthAlertJob::class, 6);

    $notification = new OperationalHealthChangedNotification(
        'health-event',
        'Example Barbershop',
        [['component' => 'queue', 'previousStatus' => 'healthy', 'status' => 'degraded']],
    );
    $email = $notification->toMail($owner)->render();

    expect($notification->toArray($owner)['message'])->toBe('Queue is Degraded.')
        ->and($notification->toWebPush($owner)['url'])->toBe('/operations/health')
        ->and($notification->webPushPermission())->toBe('system.health.view')
        ->and($email)->toContain('System health changed.')
        ->and($email)->toContain('GOOD SERVICE STAYS WITH PEOPLE')
        ->and($email)->toContain('does not include customer information');
});

it('does not alert when health state is unknown or initially healthy', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    Notification::fake();

    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $this->mock(GetOperationalHealthQuery::class, function (MockInterface $mock): void {
        $mock->shouldReceive('execute')
            ->twice()
            ->andReturn(operationalHealthSnapshot('unknown'), operationalHealthSnapshot('healthy'));
    });

    $command = app(DispatchOperationalHealthAlertsCommand::class);
    expect($command->execute())->toBeFalse()
        ->and($command->execute())->toBeFalse();

    Notification::assertNothingSent();
});

it('rechecks staff health permission before queued email and push delivery', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    Notification::fake();

    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $job = new SendOperationalHealthAlertJob(
        (int) $owner->getKey(),
        (int) $business->getKey(),
        'health-event-job',
        (string) $business->name,
        [['component' => 'queue', 'previousStatus' => 'healthy', 'status' => 'degraded']],
    );

    $job->handle();
    Notification::assertSentTo($owner, OperationalHealthChangedNotification::class, function (
        OperationalHealthChangedNotification $notification,
        array $channels,
    ): bool {
        return $channels === ['mail', WebPushChannel::class];
    });

    Notification::fake();
    $business->members()->updateExistingPivot($owner->getKey(), ['status' => 'suspended']);
    $job->handle();
    Notification::assertNothingSent();
});

/** @return array<string, mixed> */
function operationalHealthSnapshot(string $status): array
{
    return [
        'overall' => $status,
        'checked_at' => now()->toIso8601String(),
        'database' => ['status' => $status],
        'queue' => [
            'driver' => 'database',
            'status' => $status,
            'pending' => 0,
            'failed' => 0,
            'worker_status' => $status,
            'worker_heartbeat' => now()->toIso8601String(),
        ],
        'scheduler' => ['status' => $status, 'last_heartbeat' => now()->toIso8601String()],
        'whatsapp' => ['status' => $status],
        'webhooks' => ['status' => $status, 'unprocessed' => 0, 'stale_callbacks' => 0, 'failed_recent' => 0, 'last_received_at' => null],
    ];
}
