<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves the single active barbershop for an authorized web request', function (): void {
    $business = Business::factory()->create(['name' => 'Northside Barbers']);
    $user = User::factory()->create();
    $business->members()->attach($user, [
        'role' => 'viewer',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentBusiness.id', $business->getKey())
            ->where('currentBusiness.name', 'Northside Barbers'));
});

it('fails closed when the single-business configuration is missing or ambiguous', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertServiceUnavailable();

    Business::factory()->count(2)->create();

    $this->actingAs($user)->get('/dashboard')->assertServiceUnavailable();
});

it('revokes access when the user has no active barbershop membership', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $business->members()->attach($user, [
        'role' => 'viewer',
        'status' => 'suspended',
        'joined_at' => now(),
    ]);

    $this->actingAs($user)->get('/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('documents configurable application runtime controls in the environment template', function (): void {
    $environmentTemplate = file_get_contents(base_path('.env.example'));

    expect($environmentTemplate)->toBeString()
        ->toContain('WHATSAPP_WEBHOOK_MAX_BYTES=1048576')
        ->toContain('AUTOMATION_QUEUED_RECOVERY_AFTER_MINUTES=5')
        ->toContain('AUTOMATION_MINIMUM_CUSTOMER_MESSAGE_INTERVAL_HOURS=72')
        ->toContain('SESSION_SECURE_COOKIE=false');

    $deploymentInstructions = file_get_contents(base_path('docs/operations/deployment.md'));

    expect($deploymentInstructions)->toBeString()
        ->toContain('SESSION_SECURE_COOKIE=true');
});
