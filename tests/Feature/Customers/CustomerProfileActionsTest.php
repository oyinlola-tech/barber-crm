<?php

use App\Dtos\Customers\CreateCustomerDto;
use App\Jobs\WhatsApp\ProcessWhatsAppWebhookJob;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationNote;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\CustomerTag;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Models\Visit;
use App\Models\WebhookEvent;
use App\Services\Customers\Commands\AddCustomerNoteCommand;
use App\Services\Customers\Commands\AnonymizeCustomerCommand;
use App\Services\Customers\Commands\ArchiveCustomerCommand;
use App\Services\Customers\Commands\CreateCustomerCommand;
use App\Services\Customers\Commands\DeleteCustomerCommand;
use App\Services\Customers\Commands\ImportCustomersCommand;
use App\Services\Customers\Commands\RecordCustomerExportCommand;
use App\Services\Customers\Commands\RestoreCustomerCommand;
use App\Services\Customers\Commands\SyncCustomerTagsCommand;
use App\Services\Customers\Commands\UpdateCustomerCommand;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function customerActionsOwner(): User
{
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('customer-actions-test-secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

it('persists consent and opt-out transitions for the customer profile', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($user)
        ->post(route('customers.consents.store', $customer), [
            'channel' => 'whatsapp',
            'granted' => true,
            'source' => 'written',
            'purpose' => 'customer_follow_up',
            'evidence_reference' => 'consent-form-42',
        ])
        ->assertSessionHas('success', 'Consent preference recorded.');

    $firstConsent = CustomerConsent::query()->where('customer_id', $customer->getKey())->firstOrFail();
    expect($firstConsent->granted)->toBeTrue()
        ->and($firstConsent->purpose)->toBe('customer_follow_up')
        ->and($firstConsent->evidence_reference)->toBe('consent-form-42')
        ->and($firstConsent->revoked_at)->toBeNull();

    $this->post(route('customers.consents.store', $customer), [
        'channel' => 'whatsapp',
        'granted' => false,
        'source' => 'whatsapp',
    ])->assertSessionHas('success', 'Consent preference recorded.');

    $firstConsent->refresh();
    $latestConsent = CustomerConsent::query()
        ->where('customer_id', $customer->getKey())
        ->latest('id')
        ->firstOrFail();

    expect($firstConsent->revoked_at)->not->toBeNull()
        ->and($latestConsent->granted)->toBeFalse()
        ->and($latestConsent->revoked_at)->not->toBeNull()
        ->and(AuditLog::query()->where('business_id', $business->getKey())->whereIn('action', [
            'customer.consent_granted', 'customer.consent_revoked',
        ])->count())->toBe(2);
});

it('records a private customer note with an audit event', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($user)
        ->post(route('customers.notes.store', $customer), ['body' => 'Prefers a short fade.'])
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->noteEntries()->firstOrFail()->body)->toBe('Prefers a short fade.')
        ->and(AuditLog::query()->where('business_id', $business->getKey())->where('action', 'customer.note_added')->exists())->toBeTrue();
});

it('keeps private customer notes out of customer update audit metadata', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $customer = Customer::factory()->create([
        'business_id' => $business->getKey(),
        'notes' => 'Sensitive personal detail that must not be retained in audit logs.',
    ]);
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $command = app(UpdateCustomerCommand::class);

    $command->execute((int) $business->getKey(), (int) $customer->getKey(), (int) $user->getKey(), ['first_name' => 'Updated']);
    $command->execute((int) $business->getKey(), (int) $customer->getKey(), (int) $user->getKey(), [
        'notes' => 'Another sensitive note that must not be retained.',
    ]);

    $auditRows = AuditLog::query()->where('business_id', $business->getKey())->where('action', 'customer.updated')->get();
    expect($auditRows)->toHaveCount(2);
    foreach ($auditRows as $auditRow) {
        expect(json_encode([$auditRow->before, $auditRow->after]))
            ->not->toContain('Sensitive personal detail')
            ->not->toContain('Another sensitive note')
            ->not->toContain('notes');
    }
    expect($auditRows[0]->before)->toHaveKey('first_name');
});

it('creates and assigns normalized tags only within the current barbershop', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($user)
        ->put(route('customers.tags.sync', $customer), ['tags' => ['  Regular  ', 'Sensitive scalp']])
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->tags()->pluck('name')->sort()->values()->all())
        ->toBe(['regular', 'sensitive scalp'])
        ->and(CustomerTag::query()->where('business_id', $business->getKey())->count())->toBe(2)
        ->and(AuditLog::query()->where('business_id', $business->getKey())->where('action', 'customer.tags_updated')->exists())->toBeTrue();
});

it('enforces note and tag permissions when CQRS commands are called outside HTTP controllers', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $viewer = User::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $business->members()->attach($viewer, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);

    expect(fn () => app(AddCustomerNoteCommand::class)->execute(
        (int) $business->getKey(),
        (int) $customer->getKey(),
        (int) $viewer->getKey(),
        'Unauthorized note',
    ))->toThrow(HttpException::class);

    expect(fn () => app(SyncCustomerTagsCommand::class)->execute(
        (int) $business->getKey(),
        (int) $customer->getKey(),
        (int) $viewer->getKey(),
        ['unauthorized'],
    ))->toThrow(HttpException::class);

    expect($customer->noteEntries()->count())->toBe(0)
        ->and($customer->tags()->count())->toBe(0)
        ->and(AuditLog::query()->where('business_id', $business->getKey())->whereIn('action', [
            'customer.note_added', 'customer.tags_updated',
        ])->count())->toBe(0);
});

it('enforces customer permissions when mutation and export commands are invoked directly', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $viewer = User::factory()->create();
    $business->members()->attach($viewer, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);
    $activeCustomer = Customer::factory()->create(['business_id' => $business->getKey(), 'first_name' => 'Before']);
    $archivedCustomer = Customer::factory()->create(['business_id' => $business->getKey(), 'status' => 'archived', 'archived_at' => now()]);
    $createDto = new CreateCustomerDto('New', null, '+2348031234567', null, null, null, null);

    $deniedCommands = [
        fn () => app(CreateCustomerCommand::class)->execute((int) $business->getKey(), (int) $viewer->getKey(), $createDto),
        fn () => app(UpdateCustomerCommand::class)->execute((int) $business->getKey(), (int) $activeCustomer->getKey(), (int) $viewer->getKey(), ['first_name' => 'After']),
        fn () => app(ArchiveCustomerCommand::class)->execute((int) $business->getKey(), (int) $activeCustomer->getKey(), (int) $viewer->getKey()),
        fn () => app(RestoreCustomerCommand::class)->execute((int) $business->getKey(), (int) $archivedCustomer->getKey(), (int) $viewer->getKey()),
        fn () => app(AnonymizeCustomerCommand::class)->execute((int) $business->getKey(), (int) $activeCustomer->getKey(), (int) $viewer->getKey()),
        fn () => app(DeleteCustomerCommand::class)->execute((int) $business->getKey(), (int) $activeCustomer->getKey(), (int) $viewer->getKey()),
        fn () => app(ImportCustomersCommand::class)->execute((int) $business->getKey(), (int) $viewer->getKey(), []),
        fn () => app(RecordCustomerExportCommand::class)->execute((int) $business->getKey(), (int) $viewer->getKey()),
    ];

    foreach ($deniedCommands as $command) {
        expect($command)->toThrow(HttpException::class);
    }

    expect(Customer::query()->where('business_id', $business->getKey())->count())->toBe(2)
        ->and($activeCustomer->refresh()->first_name)->toBe('Before')
        ->and($activeCustomer->status)->toBe('active')
        ->and($archivedCustomer->refresh()->status)->toBe('archived')
        ->and(AuditLog::query()->where('business_id', $business->getKey())->count())->toBe(0);
});

it('does not allow import callers to grant themselves customer-note access', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $staff = User::factory()->create();
    Role::query()->create([
        'business_id' => $business->getKey(),
        'slug' => 'import-only',
        'name' => 'Import only',
        'permissions' => ['customers.import'],
    ]);
    $business->members()->attach($staff, ['role' => 'import-only', 'status' => 'active', 'joined_at' => now()]);

    $result = app(ImportCustomersCommand::class)->execute((int) $business->getKey(), (int) $staff->getKey(), [[
        'first_name' => 'Sensitive',
        'last_name' => null,
        'phone' => '+2348031234567',
        'notes' => 'Must not be imported',
    ]]);

    expect($result['created'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and(Customer::query()->where('business_id', $business->getKey())->exists())->toBeFalse();
});

it('rejects customer creation before duplicate-phone validation for users without create permission', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $business->members()->attach($user, ['role' => 'barber', 'status' => 'active', 'joined_at' => now()]);
    $existing = Customer::factory()->create(['business_id' => $business->getKey(), 'phone' => '+2348031234567']);

    $this->actingAs($user)->post(route('customers.store'), [
        'first_name' => 'Unauthorized',
        'phone' => '+2348031234567',
    ])->assertForbidden()->assertSessionDoesntHaveErrors();

    expect(Customer::query()->where('business_id', $business->getKey())->count())->toBe(1)
        ->and($existing->refresh()->phone)->toBe('+2348031234567');
});

it('denies a user with import permission but no customer-list access before processing the upload', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = User::factory()->create();
    Role::query()->create([
        'business_id' => $business->getKey(),
        'slug' => 'import-only-route-test',
        'name' => 'Import only',
        'permissions' => ['customers.import'],
    ]);
    $business->members()->attach($user, ['role' => 'import-only-route-test', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($user)->post(route('customers.import'), [
        'file' => UploadedFile::fake()->createWithContent('customers.csv', "first_name,last_name,phone,email\nImported,Customer,+2348031234567,imported@example.test\n"),
    ])->assertForbidden()->assertSessionDoesntHaveErrors();

    expect(Customer::query()->where('business_id', $business->getKey())->where('phone', '+2348031234567')->exists())->toBeFalse();

    $this->get(route('customers.index'))->assertForbidden();
});

it('prevents customer commands from changing protected fields or notes without note permission', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $receptionist = User::factory()->create();
    $business->members()->attach($receptionist, ['role' => 'receptionist', 'status' => 'active', 'joined_at' => now()]);
    $customer = Customer::factory()->create(['business_id' => $business->getKey(), 'notes' => null]);
    $createWithNotes = new CreateCustomerDto('New', null, '+2348031234567', null, null, null, 'Not permitted');

    expect(fn () => app(CreateCustomerCommand::class)->execute((int) $business->getKey(), (int) $receptionist->getKey(), $createWithNotes))
        ->toThrow(HttpException::class);
    expect(fn () => app(UpdateCustomerCommand::class)->execute((int) $business->getKey(), (int) $customer->getKey(), (int) $receptionist->getKey(), ['notes' => 'Not permitted']))
        ->toThrow(HttpException::class);
    expect(fn () => app(UpdateCustomerCommand::class)->execute((int) $business->getKey(), (int) $customer->getKey(), (int) $receptionist->getKey(), ['business_id' => $otherBusiness->getKey()]))
        ->toThrow(ValidationException::class);

    expect(Customer::query()->where('business_id', $business->getKey())->count())->toBe(1)
        ->and(Customer::query()->where('business_id', $otherBusiness->getKey())->count())->toBe(0)
        ->and($customer->refresh()->notes)->toBeNull()
        ->and(AuditLog::query()->where('business_id', $business->getKey())->count())->toBe(0);
});

it('lists archived customers and restores them through the authorized profile workflow', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $activeCustomer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $archivedCustomer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $scheduledMessages = collect(['pending', 'queued', 'sending', 'reconciliation_required'])
        ->mapWithKeys(fn (string $status): array => [$status => ScheduledMessage::query()->create([
            'business_id' => $business->getKey(),
            'customer_id' => $archivedCustomer->getKey(),
            'channel' => 'whatsapp',
            'idempotency_key' => 'archive-test:'.$status,
            'payload' => [],
            'status' => $status,
            'scheduled_at' => now()->addDay(),
        ])]);
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($user)
        ->delete(route('customers.destroy', $archivedCustomer))
        ->assertRedirect(route('customers.index'));

    expect($archivedCustomer->refresh()->status)->toBe('archived')
        ->and($archivedCustomer->archived_at)->not->toBeNull()
        ->and($scheduledMessages['pending']->fresh()->status)->toBe('cancelled')
        ->and($scheduledMessages['queued']->fresh()->status)->toBe('cancelled')
        ->and($scheduledMessages['sending']->fresh()->status)->toBe('sending')
        ->and($scheduledMessages['reconciliation_required']->fresh()->status)->toBe('reconciliation_required');
    $archiveAudit = AuditLog::query()->where('business_id', $business->getKey())
        ->where('action', 'customer.archived')
        ->where('auditable_id', $archivedCustomer->getKey())
        ->firstOrFail();
    expect($archiveAudit->after['cancelled_follow_ups'])->toBe(2);
    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'action' => 'customer.archived',
        'auditable_id' => $archivedCustomer->getKey(),
    ]);
    $this->delete(route('customers.destroy', $archivedCustomer))->assertForbidden();

    $this->get(route('customers.index', ['status' => 'archived']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->where('filters.status', 'archived')
            ->where('customers.total', 1)
            ->where('customers.data.0.id', $archivedCustomer->getKey()));

    $this->get(route('customers.show', $archivedCustomer))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.restore_customer', true)
            ->where('permissions.archive', false)
            ->where('permissions.record_visit', false));

    $this->post(route('customers.restore', $archivedCustomer))
        ->assertRedirect(route('customers.show', $archivedCustomer));

    expect($archivedCustomer->refresh()->status)->toBe('active')
        ->and($archivedCustomer->archived_at)->toBeNull()
        ->and($scheduledMessages['pending']->fresh()->status)->toBe('cancelled')
        ->and($scheduledMessages['queued']->fresh()->status)->toBe('cancelled');
    $this->post(route('customers.restore', $archivedCustomer))->assertForbidden();

    $this->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'active')
            ->where('customers.total', 2));

    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'action' => 'customer.restored',
        'auditable_id' => $archivedCustomer->getKey(),
    ]);
    expect($activeCustomer->refresh()->status)->toBe('active');
});

it('prevents staff from adding notes or tags to another business customer', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $user = customerActionsOwner();
    $customer = Customer::factory()->create(['business_id' => $otherBusiness->getKey()]);
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $business->update(['is_active' => true]);
    $otherBusiness->update(['is_active' => false]);

    $this->actingAs($user)
        ->post(route('customers.notes.store', $customer), ['body' => 'Should not be visible'])
        ->assertForbidden();

    expect($customer->noteEntries()->count())->toBe(0);
});

it('does not expose customer details outside the viewers granted permissions', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['business_id' => $business->getKey(), 'notes' => 'Private operational detail']);
    $business->members()->attach($user, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'granted' => true, 'source' => 'in_person', 'recorded_at' => now(),
    ]);
    Visit::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(),
        'recorded_by' => $user->getKey(), 'visited_at' => now()->subDay(),
    ]);
    $customer->noteEntries()->create(['business_id' => $business->getKey(), 'created_by' => $user->getKey(), 'body' => 'Private note entry']);
    $tag = CustomerTag::query()->create(['business_id' => $business->getKey(), 'name' => 'private']);
    $customer->tags()->attach($tag, ['business_id' => $business->getKey()]);

    $this->actingAs($user)->get(route('customers.show', $customer))->assertInertia(fn (Assert $page) => $page
        ->component('customers/show')
        ->where('permissions.view_consent', false)
        ->where('permissions.add_note', false)
        ->where('permissions.manage_tags', false)
        ->has('customer.consents', 0)
        ->has('customer.note_entries', 0)
        ->has('customer.tags', 0)
        ->where('customer.notes', null));
});

it('exports authorized customer data as a formula-safe CSV and audits the download', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    Customer::factory()->create([
        'business_id' => $business->getKey(),
        'first_name' => '=2+2',
        'phone' => '+2348031234567',
        'email' => 'customer@example.test',
    ]);

    $response = $this->actingAs($user)->get(route('customers.export'))->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain("'=2+2")
        ->and($csv)->toContain("'+2348031234567")
        ->and(AuditLog::query()->where('business_id', $business->getKey())->where('action', 'customers.exported')->exists())->toBeTrue();
});

it('denies customer export to staff without the export permission', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $business->members()->attach($user, ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($user)->get(route('customers.export'))->assertForbidden();
});

it('stores a customer-reported return date without creating a booking', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $returnDate = now()->addDays(14)->toDateString();
    $returnTemplate = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'name' => 'return_intention', 'language' => 'en',
        'category' => 'marketing', 'purpose' => 'return_intention', 'status' => 'synchronized',
        'provider_status' => 'APPROVED', 'is_enabled' => true, 'components' => [], 'variable_map' => [],
    ]);
    $retentionTemplate = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'name' => 'retention_reminder', 'language' => 'en',
        'category' => 'marketing', 'purpose' => 'retention', 'status' => 'synchronized',
        'provider_status' => 'APPROVED', 'is_enabled' => true, 'components' => [], 'variable_map' => [],
    ]);
    $experienceTemplate = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'name' => 'experience_check', 'language' => 'en',
        'category' => 'utility', 'purpose' => 'experience', 'status' => 'synchronized',
        'provider_status' => 'APPROVED', 'is_enabled' => true, 'components' => [], 'variable_map' => [],
    ]);
    $returnReminder = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'idempotency_key' => 'return-intention-pending', 'payload' => ['template_id' => $returnTemplate->getKey()],
        'status' => 'pending', 'scheduled_at' => now()->addDay(),
    ]);
    $retentionReminder = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'idempotency_key' => 'retention-reminder-queued', 'payload' => ['template_id' => $retentionTemplate->getKey()],
        'status' => 'queued', 'scheduled_at' => now()->addDay(),
    ]);
    $inFlightReminder = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'idempotency_key' => 'retention-reminder-sending', 'payload' => ['template_id' => $retentionTemplate->getKey()],
        'status' => 'sending', 'scheduled_at' => now(),
    ]);
    $experienceFollowUp = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'idempotency_key' => 'experience-follow-up-pending', 'payload' => ['template_id' => $experienceTemplate->getKey()],
        'status' => 'pending', 'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($user)->patch(route('customers.update', $customer), [
        'intended_return_date' => $returnDate,
    ])->assertRedirect(route('customers.show', $customer));

    expect($customer->refresh()->intended_return_date->toDateString())->toBe($returnDate)
        ->and($returnReminder->fresh()->status)->toBe('cancelled')
        ->and($retentionReminder->fresh()->status)->toBe('cancelled')
        ->and($inFlightReminder->fresh()->status)->toBe('sending')
        ->and($experienceFollowUp->fresh()->status)->toBe('pending');
    $this->assertDatabaseMissing('appointments', ['customer_id' => $customer->getKey()]);
});

it('imports only new normalized phone numbers and reports duplicates without overwriting', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $existing = Customer::factory()->create(['business_id' => $business->getKey(), 'phone' => '+2348031234567', 'first_name' => 'Original']);
    $csv = "first_name,last_name,phone,email\nChanged,Name,+2348031234567,changed@example.test\nNew,Customer,+2348031234568,new@example.test\nDuplicate,Row,08031234568,another@example.test\n";

    $this->actingAs($user)->post(route('customers.import'), [
        'file' => UploadedFile::fake()->createWithContent('customers.csv', $csv),
    ])->assertRedirect(route('customers.index'));

    $this->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->where('importResult.created', 1)
            ->where('importResult.skipped', 2));

    expect(Customer::query()->where('business_id', $business->getKey())->count())->toBe(2)
        ->and($existing->refresh()->first_name)->toBe('Original')
        ->and(AuditLog::query()->where('business_id', $business->getKey())->where('action', 'customers.imported')->value('after'))->toMatchArray(['created' => 1, 'skipped' => 2]);
});

it('does not let an invalid import row suppress a later valid row with the same phone number', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $csv = "first_name,last_name,phone,email\n,Invalid,+2348031234567,\nValid,Customer,08031234567,valid@example.test\n";

    $this->actingAs($user)->post(route('customers.import'), [
        'file' => UploadedFile::fake()->createWithContent('customers.csv', $csv),
    ])->assertRedirect(route('customers.index'));

    $this->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->where('importResult.created', 1)
            ->where('importResult.skipped', 1));

    expect(Customer::query()->where('business_id', $business->getKey())->where('phone', '+2348031234567')->value('first_name'))->toBe('Valid');
});

it('anonymizes a customer and preserves retained history without retaining message bodies or PII audit metadata', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $customer = Customer::factory()->create([
        'business_id' => $business->getKey(), 'first_name' => 'Ada', 'phone' => '+2348031234567',
        'intended_return_date' => now()->addWeek()->toDateString(),
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'granted' => true, 'source' => 'in_person', 'recorded_at' => now(),
    ]);
    $customer->noteEntries()->create(['business_id' => $business->getKey(), 'created_by' => $user->getKey(), 'body' => 'Private customer note']);
    $conversation = Conversation::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp', 'status' => 'open',
    ]);
    $message = Message::query()->create([
        'business_id' => $business->getKey(), 'conversation_id' => $conversation->getKey(), 'direction' => 'inbound', 'type' => 'text', 'status' => 'received', 'body' => 'My personal detail',
    ]);
    $conversationNote = ConversationNote::query()->create([
        'business_id' => $business->getKey(), 'conversation_id' => $conversation->getKey(),
        'created_by' => $user->getKey(), 'body' => 'Customer shared a private health detail',
    ]);
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(),
        'recorded_by' => $user->getKey(), 'visited_at' => now(), 'notes' => 'Customer shared a private personal detail',
    ]);
    $webhookEvent = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.privacy-pending',
        'event_type' => 'message',
        'payload' => [
            'kind' => 'message',
            'from' => '2348031234567',
            'body' => 'Private content in an unprocessed webhook',
            'phone_number_id' => 'barbershop-phone-id',
        ],
        'status' => 'received',
    ]);
    $unmatchedWebhookEvent = WebhookEvent::query()->create([
        'business_id' => $business->getKey(),
        'provider' => 'whatsapp',
        'event_id' => 'message:wamid.privacy-unmatched',
        'event_type' => 'message',
        'payload' => [
            'kind' => 'message',
            'from' => '2348031234567',
            'body' => 'Private content retained after connection mismatch',
            'phone_number_id' => 'unknown-phone-id',
        ],
        'status' => 'unmatched_connection',
    ]);
    ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp', 'idempotency_key' => 'pvt-1',
        'status' => 'pending', 'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($user)->post(route('customers.anonymize', $customer))->assertRedirect(route('customers.index'));
    app()->call([new ProcessWhatsAppWebhookJob((int) $webhookEvent->getKey()), 'handle']);

    expect($customer->refresh()->status)->toBe('anonymized')
        ->and($customer->first_name)->toBe('Deleted')
        ->and($customer->phone)->not->toBe('+2348031234567')
        ->and($customer->intended_return_date)->toBeNull()
        ->and($customer->consents()->count())->toBe(1)
        ->and($customer->noteEntries()->count())->toBe(0)
        ->and($message->refresh()->body)->toBeNull()
        ->and($visit->refresh()->notes)->toBeNull()
        ->and($webhookEvent->refresh()->payload)->toBe(['redacted' => true])
        ->and($webhookEvent->status)->toBe('processed')
        ->and($unmatchedWebhookEvent->refresh()->payload)->toBe(['redacted' => true])
        ->and($unmatchedWebhookEvent->status)->toBe('unmatched_connection')
        ->and($conversationNote->refresh()->body)->toBe('[redacted: customer anonymized]')
        ->and(ScheduledMessage::query()->where('customer_id', $customer->getKey())->value('status'))->toBe('cancelled')
        ->and(AuditLog::query()->where('auditable_id', $customer->getKey())->where('auditable_type', Customer::class)->where('action', 'customer.created')->value('after'))->toBeNull();
});

it('blocks permanent deletion when customer consent or operational history must be retained', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'granted' => false, 'source' => 'customer_opt_out', 'recorded_at' => now(), 'revoked_at' => now(),
    ]);

    $this->actingAs($user)->delete(route('customers.permanent-delete', $customer))->assertSessionHasErrors('customer');

    expect(Customer::query()->whereKey($customer->getKey())->exists())->toBeTrue();
});

it('refuses anonymization while a provider delivery is sending or requires reconciliation', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $message = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp',
        'idempotency_key' => 'privacy-in-flight-test', 'status' => 'sending', 'body' => 'An in-flight message body',
        'payload' => ['template_name' => 'approved_template'], 'scheduled_at' => now(),
    ]);

    $this->actingAs($user)->post(route('customers.anonymize', $customer))->assertSessionHasErrors('customer');

    expect($customer->refresh()->status)->toBe('active')
        ->and($message->refresh()->status)->toBe('sending')
        ->and($message->body)->toBe('An in-flight message body');
});
