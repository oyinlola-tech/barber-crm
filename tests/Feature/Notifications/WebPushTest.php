<?php

use App\Configs\WebPushConfig;
use App\Interfaces\Notifications\WebPushDeliveryInterface;
use App\Jobs\Notifications\SendWebPushNotificationJob;
use App\Models\Business;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Contracts\WebPushNotification;
use App\Notifications\CustomerMessageReceivedNotification;
use App\Notifications\CustomerReturnIntentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('persists only the signed-in users supported browser push endpoint encrypted at rest', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $business->members()->attach($user, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($other, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);

    $payload = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/browser-subscription-123',
        'keys' => ['p256dh' => str_repeat('a', 65), 'auth' => str_repeat('b', 24)],
    ];
    config(['webpush.public_key' => 'public-test-key', 'webpush.private_key' => 'private-test-key']);

    $this->actingAs($user)->postJson('/settings/security/web-push-subscriptions', $payload)->assertOk()->assertJson(['saved' => true]);

    $saved = WebPushSubscription::query()->firstOrFail();
    $raw = DB::table('web_push_subscriptions')->where('id', $saved->getKey())->first();
    expect($saved->user_id)->toBe($user->getKey())
        ->and($saved->endpoint)->toBe($payload['endpoint'])
        ->and($saved->public_key)->toBe($payload['keys']['p256dh'])
        ->and($raw->endpoint)->not->toBe($payload['endpoint'])
        ->and($raw->public_key)->not->toBe($payload['keys']['p256dh']);

    $this->actingAs($other)->deleteJson('/settings/security/web-push-subscriptions', ['endpoint' => $payload['endpoint']])
        ->assertOk()->assertJson(['removed' => false]);
    expect(WebPushSubscription::query()->count())->toBe(1);
});

it('rejects arbitrary push endpoints and refuses subscriptions before VAPID is configured', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $business->members()->attach($user, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);
    $payload = [
        'endpoint' => 'https://127.0.0.1/admin',
        'keys' => ['p256dh' => str_repeat('a', 65), 'auth' => str_repeat('b', 24)],
    ];

    $this->actingAs($user)->postJson('/settings/security/web-push-subscriptions', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('endpoint');

    $payload['endpoint'] = 'https://fcm.googleapis.com/fcm/send/valid';
    $this->postJson('/settings/security/web-push-subscriptions', $payload)->assertServiceUnavailable();
    expect(WebPushSubscription::query()->count())->toBe(0);
});

it('sends generic privacy-safe push payloads through the custom notification channel', function (): void {
    $user = User::factory()->create();
    $message = new CustomerMessageReceivedNotification('notice-id', 12);
    $returnDate = new CustomerReturnIntentionNotification(42, 'Private Name', '+2348012345678', '2026-12-01');

    expect($message->via($user))->toContain(WebPushChannel::class)
        ->and($message->toWebPush($user))->toBe([
            'title' => 'New WhatsApp message',
            'body' => 'A customer sent a message. Open your inbox to respond.',
            'url' => '/inbox/12',
        ])
        ->and($returnDate->toWebPush($user)['body'])->not->toContain('Private Name')
        ->and($returnDate->toWebPush($user)['body'])->not->toContain('2026-12-01')
        ->and($returnDate->toWebPush($user)['body'])->not->toContain('+2348012345678');
});

it('delivers queued push only to subscriptions owned by the recipient', function (): void {
    $business = Business::factory()->create();
    $recipient = User::factory()->create();
    $other = User::factory()->create();
    $business->members()->attach($recipient, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($other, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $subscription = WebPushSubscription::query()->create([
        'user_id' => $recipient->getKey(),
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/recipient',
        'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/fcm/send/recipient'),
        'public_key' => str_repeat('a', 65),
        'auth_token' => str_repeat('b', 24),
    ]);
    WebPushSubscription::query()->create([
        'user_id' => $other->getKey(),
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/other',
        'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/fcm/send/other'),
        'public_key' => str_repeat('c', 65),
        'auth_token' => str_repeat('d', 24),
    ]);

    config(['webpush.public_key' => 'public-test-key', 'webpush.private_key' => 'private-test-key']);
    $delivery = Mockery::mock(WebPushDeliveryInterface::class);
    $delivery->shouldReceive('send')->once()->withArgs(fn ($target, $payload): bool => $target->is($subscription) && $payload['url'] === '/inbox/8' && isset($payload['tag'])
    )->andReturnNull();

    (new SendWebPushNotificationJob((int) $recipient->getKey(), 'conversations.view', [
        'title' => 'New WhatsApp message',
        'body' => 'A customer sent a message.',
        'url' => '/inbox/8',
        'tag' => 'test-event-id',
    ]))->handle(app(WebPushConfig::class), $delivery);
});

it('rechecks active membership and current permission before push delivery', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    WebPushSubscription::query()->create([
        'user_id' => $user->getKey(),
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/suspended-user',
        'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/fcm/send/suspended-user'),
        'public_key' => str_repeat('a', 65),
        'auth_token' => str_repeat('b', 24),
    ]);
    $business->members()->updateExistingPivot($user->getKey(), ['status' => 'suspended']);

    $delivery = Mockery::mock(WebPushDeliveryInterface::class);
    $delivery->shouldNotReceive('send');
    config(['webpush.public_key' => 'public-test-key', 'webpush.private_key' => 'private-test-key']);

    (new SendWebPushNotificationJob((int) $user->getKey(), 'dashboard.view', [
        'title' => 'Activity updated',
        'body' => 'Open the shop dashboard.',
        'url' => '/dashboard',
        'tag' => 'notice-id',
    ]))->handle(app(WebPushConfig::class), $delivery);
});

it('does not allow invalid paths through a push notification channel', function (): void {
    $channel = app(WebPushChannel::class);
    $user = User::factory()->create();
    $notification = new class extends Notification implements WebPushNotification
    {
        public function toWebPush(object $notifiable): array
        {
            return ['title' => 'Notice', 'body' => 'Open the CRM.', 'url' => '//outside.example'];
        }

        public function webPushPermission(): string
        {
            return 'dashboard.view';
        }
    };

    config(['webpush.public_key' => 'public-test-key', 'webpush.private_key' => 'private-test-key']);
    Queue::fake();
    $channel->send($user, $notification);
    Queue::assertPushed(SendWebPushNotificationJob::class, fn (SendWebPushNotificationJob $job): bool => $job->payload['url'] === '/dashboard');
});
