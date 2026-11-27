<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Review;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WhatsAppConnection;
use Database\Seeders\BusinessRoleSeeder;
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

if (Artisan::call('db:seed', ['--class' => BusinessRoleSeeder::class, '--force' => true, '--no-interaction' => true]) !== 0) {
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
        'automations.create', 'automations.update', 'automations.enable', 'automations.disable',
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

$customerImportRole = Role::query()->create([
    'business_id' => $business->getKey(),
    'name' => 'Customer import and export browser tester',
    'slug' => 'customer_import_export_browser_tester',
    'permissions' => ['dashboard.view', 'customers.view', 'customers.import', 'customers.export'],
]);
$customerImporter = User::query()->create([
    'name' => 'Customer Importer',
    'email' => 'crm.importer@example.test',
    'password' => Hash::make('Browser-only importer password 9842!'),
]);
$customerImporter->forceFill(['email_verified_at' => now()])->save();
$business->members()->attach($customerImporter, [
    'role' => $customerImportRole->slug,
    'status' => 'active',
    'joined_at' => now(),
]);

foreach ([
    ['Customer Lifecycle Browser User', 'crm.customer-lifecycle@example.test'],
    ['Inbox Browser User', 'crm.inbox@example.test'],
    ['Roles Browser User', 'crm.roles@example.test'],
    ['Services Browser User', 'crm.services@example.test'],
] as [$name, $email]) {
    $browserUser = User::query()->create([
        'name' => $name,
        'email' => $email,
        'password' => Hash::make('Browser-only password 9842!'),
    ]);
    $browserUser->forceFill(['email_verified_at' => now()])->save();
    $business->members()->attach($browserUser, [
        'role' => 'browser_tester',
        'status' => 'active',
        'joined_at' => now(),
    ]);
}

$profileEditorRole = Role::query()->create([
    'business_id' => $business->getKey(),
    'name' => 'Profile editor without note access',
    'slug' => 'profile_editor_without_note_access',
    'permissions' => ['dashboard.view', 'customers.view', 'customers.update'],
]);
$profileEditor = User::query()->create([
    'name' => 'Profile Editor',
    'email' => 'crm.profile-editor@example.test',
    'email_verified_at' => now(),
    'password' => Hash::make('Browser-only editor password 9842!'),
]);
$profileEditor->forceFill(['email_verified_at' => now()])->save();
$business->members()->attach($profileEditor, [
    'role' => $profileEditorRole->slug,
    'status' => 'active',
    'joined_at' => now(),
]);
Customer::query()->create([
    'business_id' => $business->getKey(),
    'created_by' => $user->getKey(),
    'first_name' => 'Limited',
    'last_name' => 'Editor',
    'phone' => '+2348012345679',
    'status' => 'active',
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
        'browser-owner-recovery-code',
    ], JSON_THROW_ON_ERROR)),
    'two_factor_confirmed_at' => now(),
])->save();
$business->members()->attach($owner, [
    'role' => 'owner',
    'status' => 'active',
    'joined_at' => now(),
]);
$staleCallback = WebhookEvent::query()->create([
    'business_id' => $business->getKey(),
    'provider' => 'whatsapp',
    'event_id' => 'status:browser-stale-callback',
    'event_type' => 'status',
    'payload' => ['redacted' => true],
    'status' => 'awaiting_message',
]);
$staleCallback->forceFill(['updated_at' => now()->subMinutes(20)])->saveQuietly();

foreach ([
    ['Browser Messaging Owner', 'crm.messaging-owner@example.test', ['browser-messaging-recovery-code']],
    ['Browser Review Owner', 'crm.review-owner@example.test', ['browser-review-owner-recovery-code']],
    ['Browser Staff Owner', 'crm.staff-owner@example.test', ['browser-staff-owner-recovery-code']],
    ['Browser Template Owner', 'crm.template-owner@example.test', ['browser-template-owner-recovery-code']],
    ['Browser Sessions Owner', 'crm.sessions-owner@example.test', ['browser-sessions-recovery-code', 'browser-sessions-second-recovery-code']],
    ['Browser Audit Owner', 'crm.audit-owner@example.test', ['browser-audit-recovery-code']],
    ['Browser Invitation Owner', 'crm.invitation-owner@example.test', ['browser-invitation-owner-recovery-code']],
] as [$name, $email, $recoveryCode]) {
    $specializedOwner = User::query()->create([
        'name' => $name,
        'email' => $email,
        'email_verified_at' => now(),
        'password' => Hash::make('Browser-only owner password 9842!'),
    ]);
    $specializedOwner->forceFill([
        'email_verified_at' => now(),
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode([
            ...$recoveryCode,
        ], JSON_THROW_ON_ERROR)),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $business->members()->attach($specializedOwner, [
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);
}

$privacyRole = Role::query()->create([
    'business_id' => $business->getKey(),
    'name' => 'Customer privacy browser tester',
    'slug' => 'customer_privacy_browser_tester',
    'permissions' => ['dashboard.view', 'customers.view', 'customers.anonymize'],
]);
$privacyUser = User::query()->create([
    'name' => 'Customer Privacy Tester',
    'email' => 'crm.privacy@example.test',
    'password' => Hash::make('Browser-only privacy password 9842!'),
]);
$privacyUser->forceFill(['email_verified_at' => now()])->save();
$business->members()->attach($privacyUser, [
    'role' => $privacyRole->slug,
    'status' => 'active',
    'joined_at' => now(),
]);
$privacyCustomer = Customer::query()->create([
    'business_id' => $business->getKey(),
    'created_by' => $privacyUser->getKey(),
    'first_name' => 'Privacy',
    'last_name' => 'Customer',
    'phone' => '+2348076543210',
    'email' => 'private.customer@example.test',
    'notes' => 'Private note for anonymization browser coverage.',
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
Review::query()->create([
    'business_id' => $business->getKey(),
    'customer_id' => $inboxCustomer->getKey(),
    'rating' => 3,
    'body' => 'The customer shared initial feedback.',
    'source' => 'internal',
    'reviewed_at' => now()->subDay(),
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

$templateCustomer = Customer::query()->create([
    'business_id' => $business->getKey(),
    'created_by' => $user->getKey(),
    'first_name' => 'Template',
    'last_name' => 'Customer',
    'phone' => '+2348011122233',
]);
CustomerConsent::query()->create([
    'business_id' => $business->getKey(),
    'customer_id' => $templateCustomer->getKey(),
    'channel' => 'whatsapp',
    'granted' => true,
    'source' => 'in_person',
    'recorded_at' => now()->subDay(),
]);
$templateConversation = Conversation::query()->create([
    'business_id' => $business->getKey(),
    'customer_id' => $templateCustomer->getKey(),
    'channel' => 'whatsapp',
    'status' => 'open',
    'last_message_at' => now()->subHours(25),
]);
$templateConversation->forceFill([
    'last_customer_message_at' => now()->subHours(25),
])->save();
Message::query()->create([
    'business_id' => $business->getKey(),
    'conversation_id' => $templateConversation->getKey(),
    'provider_message_id' => 'wamid.browser-template-inbox',
    'direction' => 'inbound',
    'type' => 'text',
    'status' => 'received',
    'body' => 'Can you remind me to return?',
    'sent_at' => now()->subHours(25),
]);
WhatsAppConnection::query()->create([
    'business_id' => $business->getKey(),
    'phone_number_id' => 'browser-phone-number',
    'access_token' => 'browser-only-token',
    'status' => 'connected',
]);
MessageTemplate::query()->create([
    'business_id' => $business->getKey(),
    'provider_template_id' => 'browser-meta-template',
    'name' => 'browser_return_follow_up',
    'language' => 'en',
    'category' => 'UTILITY',
    'purpose' => 'return_visit',
    'status' => 'approved',
    'provider_status' => 'APPROVED',
    'is_enabled' => true,
    'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}, when might you return?']],
    'variable_map' => ['customer_name'],
]);

$uncertainCustomer = Customer::query()->create([
    'business_id' => $business->getKey(),
    'created_by' => $user->getKey(),
    'first_name' => 'Uncertain',
    'last_name' => 'Customer',
    'phone' => '+2348012233445',
]);
CustomerConsent::query()->create([
    'business_id' => $business->getKey(),
    'customer_id' => $uncertainCustomer->getKey(),
    'channel' => 'whatsapp',
    'granted' => true,
    'source' => 'in_person',
    'recorded_at' => now()->subDay(),
]);
$uncertainConversation = Conversation::query()->create([
    'business_id' => $business->getKey(),
    'customer_id' => $uncertainCustomer->getKey(),
    'channel' => 'whatsapp',
    'status' => 'open',
    'last_message_at' => now(),
]);
$uncertainConversation->forceFill(['last_customer_message_at' => now()->subHours(25)])->save();
Message::query()->create([
    'business_id' => $business->getKey(),
    'conversation_id' => $uncertainConversation->getKey(),
    'provider_message_id' => 'wamid.browser-uncertain-inbound',
    'direction' => 'inbound',
    'type' => 'text',
    'status' => 'received',
    'body' => 'I will get back to you later.',
    'sent_at' => now()->subHours(25),
]);
Message::query()->create([
    'business_id' => $business->getKey(),
    'conversation_id' => $uncertainConversation->getKey(),
    'direction' => 'outbound',
    'type' => 'template',
    'status' => 'delivery_uncertain',
    'body' => 'A previously submitted template has uncertain delivery.',
]);

$mediaCustomer = Customer::query()->create([
    'business_id' => $business->getKey(),
    'created_by' => $user->getKey(),
    'first_name' => 'Media',
    'last_name' => 'Customer',
    'phone' => '+2348012233446',
]);
$mediaConversation = Conversation::query()->create([
    'business_id' => $business->getKey(),
    'customer_id' => $mediaCustomer->getKey(),
    'channel' => 'whatsapp',
    'status' => 'open',
    'last_message_at' => now(),
]);
Message::query()->create([
    'business_id' => $business->getKey(),
    'conversation_id' => $mediaConversation->getKey(),
    'provider_message_id' => 'wamid.browser-image',
    'direction' => 'inbound',
    'type' => 'image',
    'status' => 'received',
    'body' => null,
    'payload' => null,
    'sent_at' => now(),
]);
