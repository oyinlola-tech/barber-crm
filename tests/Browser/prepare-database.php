<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

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
        'templates.view', 'reviews.view', 'analytics.view', 'audit.view', 'system.health.view', 'whatsapp.view',
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
