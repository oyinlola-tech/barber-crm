<?php

use App\Configs\SecurityConfig;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;

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
        ->toContain('SESSION_SECURE_COOKIE=false')
        ->toContain('SESSION_HTTP_ONLY=true')
        ->toContain('SESSION_SAME_SITE=lax')
        ->not->toContain("AUTOMATION_MANUAL_RETRY_LIMIT=2\nAUTOMATION_MANUAL_RETRY_LIMIT=2");

    $deploymentInstructions = file_get_contents(base_path('docs/operations/deployment.md'));

    expect($deploymentInstructions)->toBeString()
        ->toContain('SESSION_SECURE_COOKIE=true');
});

it('adds conservative browser security headers and enables HSTS only for secure requests', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $user->forceFill(['email_verified_at' => now(), 'two_factor_secret' => encrypt('security-header-secret'), 'two_factor_confirmed_at' => now()])->save();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $response = $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeaderMissing('Strict-Transport-Security');

    $policy = $response->headers->get('Content-Security-Policy');
    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("script-src 'self' 'nonce-")
        ->not->toContain("script-src 'self' 'unsafe-inline'");
    $nonce = Vite::cspNonce();
    expect($nonce)->toBeString()->not->toBeEmpty()
        ->and($policy)->toContain("'nonce-{$nonce}'");

    $secondResponse = $this->actingAs($user)->get('/dashboard')->assertOk();
    $secondPolicy = $secondResponse->headers->get('Content-Security-Policy');
    $secondNonce = Vite::cspNonce();
    expect($secondNonce)->not->toBe($nonce)
        ->and($secondPolicy)->toContain("'nonce-{$secondNonce}'");
    $secondResponse->assertSee('nonce="'.$secondNonce.'"', false);

    $this->actingAs($user)->get('https://localhost/dashboard')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    config(['security.hsts_max_age' => 0]);
    $this->actingAs($user)->get('https://localhost/dashboard')
        ->assertOk()
        ->assertHeaderMissing('Strict-Transport-Security');

    config(['security.content_security_policy' => ['default-src' => ["'self'; injected-header: evil"]]]);
    expect(fn () => app(SecurityConfig::class)->contentSecurityPolicy())
        ->toThrow(InvalidArgumentException::class);
});
