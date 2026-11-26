<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

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
    $this->actingAs($viewer)->get('/operations/health')->assertForbidden();
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

it('returns a minimal readiness response without internal health details', function (): void {
    $this->getJson('/ready')
        ->assertOk()
        ->assertExactJson(['status' => 'ready'])
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('records scheduler and actual queue worker heartbeats when the heartbeat command runs', function (): void {
    Artisan::call('operations:record-heartbeat');

    $this->assertDatabaseHas('operational_heartbeats', ['component' => 'scheduler']);
    $this->assertDatabaseHas('operational_heartbeats', ['component' => 'queue_worker']);
});

function confirmOperationsMfa(User $user): void
{
    $user->forceFill([
        'two_factor_secret' => encrypt('operations-test-secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();
}
