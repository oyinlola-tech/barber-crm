<?php

use App\Interfaces\Apis\MessagingProviderInterface;
use App\Jobs\Notifications\SendScheduledMessageFailureAlertJob;
use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Notifications\ScheduledMessageFailedNotification;
use App\Services\Automations\Commands\ProcessScheduledMessageCommand;
use App\Services\Automations\Commands\RecordScheduledMessageJobFailureCommand;
use App\Services\Notifications\Commands\NotifyStaffOfScheduledMessageFailureCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('alerts only active staff with message access when a scheduled send definitively fails', function (): void {
    Queue::fake();
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'provider_template_id' => 'failure-alert-template',
        'name' => 'visit_follow_up',
        'language' => 'en',
        'category' => 'utility',
        'purpose' => 'thank_you',
        'status' => 'synchronized',
        'provider_status' => 'APPROVED',
        'is_enabled' => true,
        'components' => [['type' => 'BODY', 'text' => 'Thanks']],
        'variable_map' => [],
    ]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Visit follow-up',
        'trigger' => 'visit_completed',
        'action' => 'send_template',
        'delay_minutes' => 5,
        'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey()],
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'granted' => true,
        'source' => 'test',
        'recorded_at' => now(),
    ]);
    $scheduledMessage = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'automation_rule_id' => $rule->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'terminal-failure:connection',
        'payload' => [
            'template_id' => $template->getKey(),
            'template_name' => $template->name,
            'language' => $template->language,
            'max_attempts' => 3,
        ],
        'status' => 'queued',
        'scheduled_at' => now(),
    ]);

    $owner = User::factory()->create();
    $authorizedStaff = User::factory()->create();
    $unauthorizedStaff = User::factory()->create();
    $suspendedOwner = User::factory()->create();
    $role = Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Message history reader',
        'slug' => 'message_history_reader',
        'permissions' => ['messages.view'],
    ]);
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($authorizedStaff, ['role' => $role->slug, 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($unauthorizedStaff, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($suspendedOwner, ['role' => 'owner', 'status' => 'suspended', 'joined_at' => now()]);

    app(ProcessScheduledMessageCommand::class)->execute((int) $scheduledMessage->getKey());

    expect($scheduledMessage->fresh()->status)->toBe('failed')
        ->and($scheduledMessage->fresh()->last_error)->toBe('whatsapp_connection_incomplete');
    $notifications = DB::table('notifications')->get();
    expect($notifications->pluck('notifiable_id')->map(fn ($id): int => (int) $id)->all())
        ->toEqualCanonicalizing([(int) $owner->getKey(), (int) $authorizedStaff->getKey()]);
    Queue::assertPushedTimes(SendScheduledMessageFailureAlertJob::class, 2);

    app(ProcessScheduledMessageCommand::class)->execute((int) $scheduledMessage->getKey());
    app(NotifyStaffOfScheduledMessageFailureCommand::class)
        ->execute((int) $business->getKey(), (int) $scheduledMessage->getKey());

    expect(DB::table('notifications')->count())->toBe(2);
    Queue::assertPushedTimes(SendScheduledMessageFailureAlertJob::class, 2);
});

it('alerts only after bounded queue processing reaches a terminal failure', function (): void {
    Queue::fake();
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $owner = User::factory()->create();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $terminal = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'queue-failure:terminal',
        'payload' => ['queue_attempts' => 0, 'max_attempts' => 1],
        'status' => 'queued',
        'scheduled_at' => now(),
    ]);
    $retryable = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'queue-failure:retryable',
        'payload' => ['queue_attempts' => 0, 'max_attempts' => 3],
        'status' => 'queued',
        'scheduled_at' => now(),
    ]);

    $command = app(RecordScheduledMessageJobFailureCommand::class);
    $command->execute((int) $terminal->getKey());
    $command->execute((int) $retryable->getKey());

    expect($terminal->fresh()->status)->toBe('failed')
        ->and($retryable->fresh()->status)->toBe('pending')
        ->and(DB::table('notifications')->count())->toBe(1);
    Queue::assertPushedTimes(SendScheduledMessageFailureAlertJob::class, 1);
});

it('rechecks current message permission before emailing a failure alert', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $role = Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Message history reader',
        'slug' => 'message_history_reader',
        'permissions' => ['messages.view'],
    ]);
    $business->members()->attach($user, ['role' => $role->slug, 'status' => 'active', 'joined_at' => now()]);
    $role->update(['permissions' => []]);

    Notification::fake();
    (new SendScheduledMessageFailureAlertJob((int) $user->getKey(), (int) $business->getKey(), 'alert-id'))
        ->handle();

    Notification::assertNothingSent();
});

it('does not alert staff while a provider rate limit remains eligible for retry', function (): void {
    Queue::fake();
    config(['whatsapp.api_version' => 'v99.0']);
    $business = Business::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'provider_template_id' => 'rate-limit-template',
        'name' => 'visit_follow_up', 'language' => 'en', 'category' => 'utility', 'purpose' => 'thank_you',
        'status' => 'synchronized', 'provider_status' => 'APPROVED', 'is_enabled' => true,
        'components' => [['type' => 'BODY', 'text' => 'Thanks']], 'variable_map' => [],
    ]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(), 'name' => 'Visit follow-up', 'trigger' => 'visit_completed',
        'action' => 'send_template', 'delay_minutes' => 5, 'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey()],
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'granted' => true, 'source' => 'test', 'recorded_at' => now(),
    ]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(), 'phone_number_id' => 'phone-number-1',
        'business_account_id' => 'business-account-1', 'access_token' => 'encrypted-token', 'status' => 'connected',
    ]);
    $scheduledMessage = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(),
        'automation_rule_id' => $rule->getKey(), 'channel' => 'whatsapp', 'idempotency_key' => 'rate-limit-retry',
        'payload' => [
            'template_id' => $template->getKey(), 'template_name' => $template->name,
            'language' => $template->language, 'max_attempts' => 3,
        ],
        'status' => 'queued', 'scheduled_at' => now(), 'attempts' => 1,
    ]);

    $exception = new RequestException(new Response(new GuzzleHttp\Psr7\Response(429)));
    $messaging = Mockery::mock(MessagingProviderInterface::class);
    $messaging->shouldReceive('sendTemplate')->once()->andThrow($exception);
    app()->instance(MessagingProviderInterface::class, $messaging);

    app(ProcessScheduledMessageCommand::class)->execute((int) $scheduledMessage->getKey());

    expect($scheduledMessage->fresh()->status)->toBe('pending')
        ->and($scheduledMessage->fresh()->last_error)->toBe('provider_rate_limited')
        ->and(DB::table('notifications')->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('delivers the alert to authorized staff while their access is still active', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $role = Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Message history reader',
        'slug' => 'message_history_reader',
        'permissions' => ['messages.view'],
    ]);
    $business->members()->attach($user, ['role' => $role->slug, 'status' => 'active', 'joined_at' => now()]);
    Notification::fake();

    (new SendScheduledMessageFailureAlertJob((int) $user->getKey(), (int) $business->getKey(), 'alert-id'))->handle();

    Notification::assertSentTo($user, ScheduledMessageFailedNotification::class);
});

it('keeps failure alerts generic and includes the shared branded email footer', function (): void {
    $user = User::factory()->create();
    $notification = new ScheduledMessageFailedNotification('alert-id');
    $html = $notification->toMail($user)->render();

    expect($notification->via($user))->toBe(['database'])
        ->and($notification->webPushPermission())->toBe('messages.view')
        ->and($html)->toContain('A scheduled follow-up could not be sent.')
        ->and($html)->toContain('Good service')
        ->and($html)->toContain('Review message history')
        ->and($notification->toArray($user)['url'])->toBe('/messages/scheduled');
});
