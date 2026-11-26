<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Fortify;

$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (app()->environment() !== 'testing' || config('database.default') !== 'sqlite') {
    fwrite(STDERR, "Browser database setup only runs against the isolated testing SQLite connection.\n");
    exit(1);
}

$databasePath = config('database.connections.sqlite.database');
$expectedPath = base_path('storage/framework/playwright.sqlite');
if (! is_string($databasePath) || $databasePath !== $expectedPath || is_link($expectedPath)) {
    fwrite(STDERR, "Refusing to reset a database outside the dedicated Playwright SQLite file.\n");
    exit(1);
}

if (! is_dir(dirname($expectedPath)) && ! mkdir(dirname($expectedPath), 0775, true) && ! is_dir(dirname($expectedPath))) {
    fwrite(STDERR, "Could not create the Playwright database directory.\n");
    exit(1);
}

if (is_file($expectedPath) && ! unlink($expectedPath)) {
    fwrite(STDERR, "Could not reset the isolated Playwright database.\n");
    exit(1);
}

if (Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]) !== 0) {
    fwrite(STDERR, Artisan::output());
    exit(1);
}

RateLimiter::clear('crm.browser@example.test|127.0.0.1');

$business = Business::query()->create([
    'name' => 'Browser Test Barbershop',
    'slug' => 'browser-test-barbershop',
    'timezone' => 'Africa/Lagos',
    'currency' => 'NGN',
    'is_active' => true,
]);

Role::query()->create([
    'business_id' => $business->getKey(),
    'name' => 'Browser tester',
    'slug' => 'browser_tester',
    'permissions' => [
        'dashboard.view', 'customers.view', 'customers.create', 'customers.update',
        'customers.note', 'customers.tags.manage', 'customers.archive', 'customers.restore',
        'consent.view', 'consent.record', 'consent.revoke',
        'visits.view', 'visits.create', 'visits.update', 'visits.delete', 'visits.restore',
        'roles.view', 'staff.view', 'settings.view', 'conversations.view', 'automations.view',
        'templates.view', 'messages.view', 'reviews.view', 'analytics.view', 'audit.view', 'system.health.view', 'whatsapp.view',
        'services.view', 'services.create', 'services.update',
        'services.categories.create', 'services.categories.update', 'services.categories.delete',
        'conversations.reply', 'conversations.assign', 'conversations.close',
    ],
]);

$user = User::query()->create([
    'name' => 'Browser Test User',
    'email' => 'crm.browser@example.test',
    'email_verified_at' => now(),
    'password' => Hash::make('Browser-only password 9842!'),
]);
$user->forceFill(['email_verified_at' => now()])->save();
$business->members()->attach($user, [
    'role' => 'browser_tester',
    'status' => 'active',
    'joined_at' => now(),
]);

$owner = User::query()->create([
    'name' => 'Browser Test Owner',
    'email' => 'crm.owner@example.test',
    'email_verified_at' => now(),
    'password' => Hash::make('Browser-only owner password 9842!'),
]);
$owner->forceFill([
    'email_verified_at' => now(),
    'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
    'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode([
        'browser-recovery-code',
        'browser-staff-recovery-code',
        'browser-template-recovery-code',
    ], JSON_THROW_ON_ERROR)),
    'two_factor_confirmed_at' => now(),
])->save();
$business->members()->attach($owner, [
    'role' => 'owner',
    'status' => 'active',
    'joined_at' => now(),
]);

$barber = User::factory()->create(['name' => 'Browser Test Barber']);
$business->members()->attach($barber, [
    'role' => 'barber',
    'status' => 'active',
    'joined_at' => now(),
]);
StaffProfile::query()->create([
    'business_id' => $business->getKey(),
    'user_id' => $barber->getKey(),
    'name' => $barber->name,
    'email' => $barber->email,
    'status' => 'active',
]);
$inboxCustomer = Customer::query()->create([
    'business_id' => $business->getKey(),
    'created_by' => $user->getKey(),
    'first_name' => 'Inbox',
    'last_name' => 'Customer',
    'phone' => '+2348098765432',
]);
$conversation = Conversation::query()->create([
    'business_id' => $business->getKey(),
    'customer_id' => $inboxCustomer->getKey(),
    'channel' => 'whatsapp',
    'status' => 'open',
    'last_message_at' => now(),
]);
$conversation->forceFill([
    'unread_count' => 1,
    'last_customer_message_at' => now(),
])->save();
Message::query()->create([
    'business_id' => $business->getKey(),
    'conversation_id' => $conversation->getKey(),
    'provider_message_id' => 'wamid.browser-inbox',
    'direction' => 'inbound',
    'type' => 'text',
    'status' => 'received',
    'body' => 'I had a great haircut.',
    'sent_at' => now(),
]);
