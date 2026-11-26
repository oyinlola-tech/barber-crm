<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\CustomerTag;
use App\Models\Message;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Models\Visit;
use App\Services\Customers\Commands\AddCustomerNoteCommand;
use App\Services\Customers\Commands\SyncCustomerTagsCommand;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

it('lists archived customers and restores them through the authorized profile workflow', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $user = customerActionsOwner();
    $activeCustomer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $archivedCustomer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($user)
        ->delete(route('customers.destroy', $archivedCustomer))
        ->assertRedirect(route('customers.index'));

    expect($archivedCustomer->refresh()->status)->toBe('archived')
        ->and($archivedCustomer->archived_at)->not->toBeNull();
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
        ->and($archivedCustomer->archived_at)->toBeNull();
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

    $this->actingAs($user)->patch(route('customers.update', $customer), [
        'intended_return_date' => $returnDate,
    ])->assertRedirect(route('customers.show', $customer));

    expect($customer->refresh()->intended_return_date->toDateString())->toBe($returnDate);
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

    expect(Customer::query()->where('business_id', $business->getKey())->count())->toBe(2)
        ->and($existing->refresh()->first_name)->toBe('Original')
        ->and(AuditLog::query()->where('business_id', $business->getKey())->where('action', 'customers.imported')->value('after'))->toMatchArray(['created' => 1, 'skipped' => 2]);
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
    ScheduledMessage::query()->create([
        'business_id' => $business->getKey(), 'customer_id' => $customer->getKey(), 'channel' => 'whatsapp', 'idempotency_key' => 'pvt-1',
        'status' => 'pending', 'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($user)->post(route('customers.anonymize', $customer))->assertRedirect(route('customers.index'));

    expect($customer->refresh()->status)->toBe('anonymized')
        ->and($customer->first_name)->toBe('Deleted')
        ->and($customer->phone)->not->toBe('+2348031234567')
        ->and($customer->intended_return_date)->toBeNull()
        ->and($customer->consents()->count())->toBe(1)
        ->and($customer->noteEntries()->count())->toBe(0)
        ->and($message->refresh()->body)->toBeNull()
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
