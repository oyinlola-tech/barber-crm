<?php

use App\Models\Business;
use App\Models\User;
use App\Notifications\StaffAccessChangedNotification;
use App\Services\Notifications\Commands\NotifyBusinessAdminsOfStaffAccessChangeCommand;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('stores an idempotent staff access alert for the owner and audit-authorized staff only', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    Mail::fake();

    $business = Business::factory()->create();
    $owner = User::factory()->create(['email' => 'owner@barber.test']);
    $actor = User::factory()->create(['email' => 'actor@barber.test']);
    $auditor = User::factory()->create(['email' => 'auditor@barber.test']);
    $receptionist = User::factory()->create(['email' => 'reception@barber.test']);

    foreach ([
        [$owner, 'owner'],
        [$actor, 'manager'],
        [$auditor, 'manager'],
        [$receptionist, 'receptionist'],
    ] as [$member, $role]) {
        $business->members()->attach($member, [
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    $auditLogId = (int) DB::table('audit_logs')->insertGetId([
        'business_id' => $business->getKey(),
        'user_id' => $actor->getKey(),
        'action' => 'staff.suspended',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $command = app(NotifyBusinessAdminsOfStaffAccessChangeCommand::class);
    $command->execute((int) $business->getKey(), (int) $actor->getKey(), $auditLogId, 'Barber Sam', 'Access was suspended for Barber Sam.');
    $command->execute((int) $business->getKey(), (int) $actor->getKey(), $auditLogId, 'Barber Sam', 'Access was suspended for Barber Sam.');

    $notifications = DB::table('notifications')->get();
    expect($notifications)->toHaveCount(2)
        ->and($notifications->pluck('notifiable_id')->sort()->values()->all())->toBe([
            $owner->getKey(),
            $auditor->getKey(),
        ])
        ->and($notifications->pluck('type')->unique()->all())->toBe([StaffAccessChangedNotification::class]);

    $notification = json_decode($notifications->first()->data, true, flags: JSON_THROW_ON_ERROR);
    expect($notification['title'])->toBe('Staff access changed')
        ->and($notification['message'])->toBe('Access was suspended for Barber Sam.')
        ->and($notification['url'])->toBe('/operations/audit');

    $emailNotification = new StaffAccessChangedNotification(
        'stable-notification-id',
        'Barber Sam',
        'Access was suspended for Barber Sam.',
        '/operations/audit',
    );
    expect($emailNotification->via($owner))->toBe(['database', 'mail'])
        ->and($emailNotification->toMail($owner)->subject)->toBe('Staff access changed')
        ->and($emailNotification->toMail($owner)->view)->toBe('mail.notifications.staff-access-changed');
});
