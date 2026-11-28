<?php

use App\Dtos\Audit\DailyActivityDigestDto;
use App\Jobs\Notifications\SendDailyActivityDigestJob;
use App\Models\Business;
use App\Models\User;
use App\Notifications\DailyActivityDigestNotification;
use App\Services\Audit\Queries\GetDailyActivityDigestQuery;
use App\Services\Notifications\Commands\DeliverDailyActivityDigestCommand;
use App\Services\Notifications\Commands\DispatchDailyActivityDigestCommand;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches at most one daily digest job for the active business and date', function (): void {
    $business = Business::factory()->create(['timezone' => 'Africa/Lagos']);
    Queue::fake();

    $command = app(DispatchDailyActivityDigestCommand::class);

    expect($command->execute())->toBeTrue()
        ->and($command->execute())->toBeFalse();

    Queue::assertPushed(SendDailyActivityDigestJob::class, 1);
});

it('summarizes only action counts for active owner and audit-authorized staff', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    Notification::fake();

    $business = Business::factory()->create(['timezone' => 'Africa/Lagos']);
    $owner = User::factory()->create();
    $auditor = User::factory()->create();
    $receptionist = User::factory()->create();
    $suspendedOwner = User::factory()->create();

    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($auditor, ['role' => 'manager', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($receptionist, ['role' => 'receptionist', 'status' => 'active', 'joined_at' => now()]);
    $business->members()->attach($suspendedOwner, ['role' => 'owner', 'status' => 'suspended', 'joined_at' => now()]);

    $yesterday = now('Africa/Lagos')->subDay();
    foreach (['customer.created', 'customer.created', 'customer.consent_updated'] as $action) {
        DB::table('audit_logs')->insert([
            'business_id' => $business->getKey(),
            'user_id' => $auditor->getKey(),
            'action' => $action,
            'before' => json_encode(['phone' => '+2348012345678']),
            'after' => json_encode(['message' => 'Private customer conversation text']),
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);
    }

    app(DeliverDailyActivityDigestCommand::class)->execute(
        (int) $business->getKey(),
        $yesterday->toDateString(),
    );

    Notification::assertSentTo($owner, DailyActivityDigestNotification::class);
    Notification::assertSentTo($auditor, DailyActivityDigestNotification::class);
    Notification::assertNotSentTo($receptionist, DailyActivityDigestNotification::class);
    Notification::assertNotSentTo($suspendedOwner, DailyActivityDigestNotification::class);

    $notification = new DailyActivityDigestNotification('digest-id', app(GetDailyActivityDigestQuery::class)
        ->execute((int) $business->getKey(), $yesterday->toDateString(), 'Africa/Lagos'));
    $mail = $notification->toMail($owner);

    expect($mail->subject)->toBe("Daily activity digest for {$yesterday->toDateString()}")
        ->and($mail->view)->toBe('mail.notifications.daily-activity-digest')
        ->and($notification->toArray($owner)['message'])->toContain('3 recorded business activities')
        ->and(json_encode($notification->toArray($owner)))->not->toContain('Private customer conversation text')
        ->and(json_encode($notification->toArray($owner)))->not->toContain('+2348012345678');
});

it('does not send an empty digest and checks for an active business before processing', function (): void {
    Notification::fake();
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    app(DeliverDailyActivityDigestCommand::class)->execute((int) $business->getKey(), now()->subDay()->toDateString());
    Notification::assertNothingSent();

    $business->update(['is_active' => false]);
    expect(app(DispatchDailyActivityDigestCommand::class)->execute())->toBeFalse();
});

it('renders the branded activity digest with an audit link and privacy statement', function (): void {
    $digest = new DailyActivityDigestDto(
        businessId: 1,
        businessName: 'Example Barber',
        date: '2026-11-27',
        actionCounts: ['customer.created' => 2],
        totalEvents: 2,
    );
    $notification = new DailyActivityDigestNotification('stable-digest-id', $digest);

    expect($notification->via(new stdClass))->toBe(['database', 'mail'])
        ->and($notification->toMail(new stdClass)->view)->toBe('mail.notifications.daily-activity-digest')
        ->and($notification->toMail(new stdClass)->viewData['actions'])->toBe([['label' => 'Customer Created', 'count' => 2]])
        ->and(view('mail.notifications.daily-activity-digest', $notification->toMail(new stdClass)->viewData)->render())
        ->toContain('Review the activity', 'excludes customer message contents');
});

it('persists one notification per recipient when the digest delivery is retried', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    Mail::fake();

    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $date = now($business->timezone)->subDay()->toDateString();
    $createdAt = now($business->timezone)->subDay();

    DB::table('audit_logs')->insert([
        'business_id' => $business->getKey(),
        'user_id' => $owner->getKey(),
        'action' => 'staff.profile_updated',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    $command = app(DeliverDailyActivityDigestCommand::class);
    $command->execute((int) $business->getKey(), $date);
    $command->execute((int) $business->getKey(), $date);

    $stored = $owner->notifications()->firstOrFail();
    expect($owner->notifications()->count())->toBe(1)
        ->and($stored->data['_delivery']['email_sent_at'])->toBeString();
});
