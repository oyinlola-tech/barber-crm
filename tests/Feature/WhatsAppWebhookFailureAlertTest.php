<?php

use App\Jobs\Notifications\SendWhatsAppWebhookFailureAlertJob;
use App\Jobs\WhatsApp\ProcessWhatsAppWebhookJob;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\WhatsAppWebhookFailedNotification;
use App\Services\Webhooks\Commands\MarkWhatsAppWebhookFailedCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('redacts terminal webhook failures and alerts only active health-authorized staff', function (): void {
    Queue::fake();
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $authorized = User::factory()->create();
    $unauthorized = User::factory()->create();
    $suspended = User::factory()->create();
    $healthReader = Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Health reader',
        'slug' => 'health_reader',
        'permissions' => ['system.health.view'],
    ]);
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($authorized, ['role' => $healthReader->slug, 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($unauthorized, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($suspended, ['role' => 'owner', 'status' => 'suspended', 'joined_at' => now()]);
    $event = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'message:terminal-failure',
        'event_type' => 'message',
        'payload' => ['kind' => 'message', 'body' => 'private customer content'],
        'status' => 'received',
    ]);

    app(MarkWhatsAppWebhookFailedCommand::class)->execute((int) $event->getKey());

    expect($event->fresh()->status)->toBe('failed')
        ->and($event->fresh()->payload)->toBe(['redacted' => true])
        ->and($event->fresh()->processed_at)->not->toBeNull()
        ->and(DB::table('notifications')->pluck('notifiable_id')->map(fn ($id): int => (int) $id)->all())
        ->toEqualCanonicalizing([(int) $owner->getKey(), (int) $authorized->getKey()]);
    Queue::assertPushedTimes(SendWhatsAppWebhookFailureAlertJob::class, 2);

    app(MarkWhatsAppWebhookFailedCommand::class)->execute((int) $event->getKey());
    expect(DB::table('notifications')->count())->toBe(2);
    Queue::assertPushedTimes(SendWhatsAppWebhookFailureAlertJob::class, 2);
});

it('does not deliver a queued webhook failure alert after health permission is revoked', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $role = Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Health reader',
        'slug' => 'health_reader',
        'permissions' => ['system.health.view'],
    ]);
    $business->members()->attach($user, ['role' => $role->slug, 'status' => 'active', 'joined_at' => now()]);
    $role->update(['permissions' => []]);

    Notification::fake();
    (new SendWhatsAppWebhookFailureAlertJob((int) $user->getKey(), (int) $business->getKey(), 'failure-id'))->handle();

    Notification::assertNothingSent();
});

it('uses a branded privacy-safe email for terminal webhook failures', function (): void {
    config(['app.url' => 'https://crm.example.test']);
    $mail = (new WhatsAppWebhookFailedNotification('failure-id'))->toMail(User::factory()->make());
    $rendered = view('mail.notifications.whatsapp-webhook-failed', $mail->viewData)->render();

    expect($mail->subject)->toBe('WhatsApp webhook processing needs attention')
        ->and($rendered)->toContain('WhatsApp event processing needs attention')
        ->and($rendered)->toContain('Review system health')
        ->and($rendered)->toContain('https://crm.example.test/operations/health')
        ->and($rendered)->not->toContain('customer message body')
        ->and($rendered)->toContain('Good service');
});

it('uses CQRS alert command when the queue job reaches terminal failure', function (): void {
    Queue::fake();
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $event = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'message:queue-terminal-failure',
        'event_type' => 'message',
        'payload' => ['kind' => 'message', 'body' => 'private customer content'],
        'status' => 'received',
    ]);

    (new ProcessWhatsAppWebhookJob((int) $event->getKey()))->failed(new RuntimeException('Private provider response'));

    expect($event->fresh()->payload)->toBe(['redacted' => true])
        ->and(DB::table('notifications')->count())->toBe(1);
    Queue::assertPushed(SendWhatsAppWebhookFailureAlertJob::class);
});
