<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Services\Operations\Queries\GetOperationalHealthQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

uses(RefreshDatabase::class);

function attachOperationsMember(Business $business, User $user, string $role): void
{
    $business->members()->attach($user, [
        'role' => $role,
        'status' => 'active',
        'joined_at' => now(),
    ]);
}

it('shows an authorized audit log without exposing stored change payloads and scopes it to the barbershop', function (): void {
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create(['is_active' => false]);
    $owner = User::factory()->create();
    attachOperationsMember($business, $owner, 'owner');
    confirmOperationsMfa($owner);
    AuditLog::query()->create([
        'business_id' => $business->getKey(),
        'user_id' => $owner->getKey(),
        'action' => 'customer.updated',
        'auditable_type' => 'App\\Models\\Customer',
        'auditable_id' => 88,
        'before' => ['access_token' => 'secret-before'],
        'after' => ['message_body' => 'private customer message'],
        'ip_address' => '192.0.2.2',
    ]);
    AuditLog::query()->create([
        'business_id' => $otherBusiness->getKey(),
        'action' => 'customer.deleted',
    ]);

    $response = $this->actingAs($owner)->get('/operations/audit');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/audit')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'customer.updated')
            ->where('logs.data.0.resource', 'Customer')
            ->missing('logs.data.0.before')
            ->missing('logs.data.0.after')
            ->where('canExport', true)
            ->where('filters.action', ''));
    expect($response->getContent())
        ->not->toContain('secret-before')
        ->not->toContain('private customer message');
});

it('denies audit and operational health pages without their respective permissions', function (): void {
    $business = Business::factory()->create();
    $viewer = User::factory()->create();
    attachOperationsMember($business, $viewer, 'viewer');

    $this->actingAs($viewer)->get('/operations/audit')->assertForbidden();
    $this->get('/operations/audit/export')->assertForbidden();
    $this->actingAs($viewer)->get('/operations/health')->assertForbidden();
});

it('exports only safe audit columns as a private formula-safe csv and records the export', function (): void {
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    attachOperationsMember($business, $owner, 'owner');
    confirmOperationsMfa($owner);
    AuditLog::query()->create([
        'business_id' => $business->getKey(),
        'user_id' => $owner->getKey(),
        'action' => '=HYPERLINK("https://example.test")',
        'auditable_type' => 'App\\Models\\Customer',
        'auditable_id' => 15,
        'before' => ['private' => 'must not export'],
        'after' => ['token' => 'secret'],
    ]);
    $otherBusiness = Business::factory()->create(['is_active' => false]);
    AuditLog::query()->create([
        'business_id' => $otherBusiness->getKey(),
        'action' => 'foreign.audit.entry',
    ]);

    $response = $this->actingAs($owner)->get('/operations/audit/export');
    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $csv = $response->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")->toContain('Customer')
        ->not->toContain('must not export')
        ->not->toContain('secret')
        ->not->toContain('foreign.audit.entry');
    $this->assertDatabaseHas('audit_logs', ['business_id' => $business->getKey(), 'user_id' => $owner->getKey(), 'action' => 'audit.exported']);
});

it('shows operational health only to authorized staff and reports recorded heartbeats', function (): void {
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    attachOperationsMember($business, $owner, 'owner');
    confirmOperationsMfa($owner);
    Artisan::call('operations:record-heartbeat');

    $this->actingAs($owner)->get('/operations/health')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/health')
            ->where('health.scheduler.status', 'healthy')
            ->where('health.queue.worker_heartbeat', fn ($timestamp) => is_string($timestamp))
            ->where('health.whatsapp.status', 'degraded'));
});

it('returns unavailable readiness when required operational heartbeats are missing', function (): void {
    $this->getJson('/ready')
        ->assertServiceUnavailable()
        ->assertExactJson(['status' => 'unavailable'])
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('requires fresh scheduler and queue worker heartbeats for readiness', function (): void {
    DB::table('operational_heartbeats')->insert([
        ['component' => 'scheduler', 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ['component' => 'queue_worker', 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->getJson('/ready')->assertOk()->assertExactJson(['status' => 'ready']);

    DB::table('operational_heartbeats')->where('component', 'scheduler')->update(['last_seen_at' => now()->subMinutes(4)]);
    $this->getJson('/ready')->assertServiceUnavailable()->assertExactJson(['status' => 'unavailable']);

    DB::table('operational_heartbeats')->where('component', 'scheduler')->update(['last_seen_at' => now()]);
    DB::table('operational_heartbeats')->where('component', 'queue_worker')->update(['last_seen_at' => now()->subMinutes(4)]);
    $this->getJson('/ready')->assertServiceUnavailable()->assertExactJson(['status' => 'unavailable']);
});

it('reports database failure without querying unavailable operational tables', function (): void {
    DB::shouldReceive('select')
        ->once()
        ->with('select 1')
        ->andThrow(new RuntimeException('database unavailable'));

    $health = app(GetOperationalHealthQuery::class)->execute(1);

    expect($health['overall'])->toBe('unhealthy')
        ->and($health['database']['status'])->toBe('unhealthy')
        ->and($health['queue']['status'])->toBe('unknown')
        ->and($health['scheduler']['status'])->toBe('unknown')
        ->and($health['whatsapp']['status'])->toBe('unknown')
        ->and($health['webhooks']['status'])->toBe('unknown');
});

it('does not report a connected WhatsApp record healthy when its runtime API configuration is incomplete', function (): void {
    $business = Business::factory()->create();
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(),
        'phone_number_id' => 'phone-id',
        'business_account_id' => 'business-account-id',
        'access_token' => 'stored-token',
        'status' => 'connected',
    ]);
    config(['whatsapp.api_version' => null]);

    $health = app(GetOperationalHealthQuery::class)->execute((int) $business->getKey());
    expect($health['whatsapp']['status'])->toBe('degraded');

    config(['whatsapp.api_version' => 'v22.0']);
    $health = app(GetOperationalHealthQuery::class)->execute((int) $business->getKey());
    expect($health['whatsapp']['status'])->toBe('healthy');
});

it('records scheduler and actual queue worker heartbeats when the heartbeat command runs', function (): void {
    config(['queue.default' => 'database']);
    Artisan::call('operations:record-heartbeat');

    $this->assertDatabaseHas('operational_heartbeats', ['component' => 'scheduler']);
    $this->assertDatabaseHas('jobs', ['queue' => 'default']);
    $this->assertDatabaseMissing('operational_heartbeats', ['component' => 'queue_worker']);

    $health = app(GetOperationalHealthQuery::class)->execute(1);
    expect($health['queue']['status'])->toBe('unhealthy');

    Artisan::call('queue:work', ['--once' => true, '--queue' => 'default']);

    $this->assertDatabaseHas('operational_heartbeats', ['component' => 'queue_worker']);
    expect(DB::table('jobs')->count())->toBe(0);
});

function confirmOperationsMfa(User $user): void
{
    $user->forceFill([
        'two_factor_secret' => encrypt('operations-test-secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();
}
