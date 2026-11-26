<?php

use App\Models\Business;
use App\Models\User;
use App\Models\WhatsAppConnection;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;

uses(RefreshDatabase::class);

function whatsappSettingsOwner(string $role = 'owner'): array
{
    test()->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create(['is_active' => true]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt('[]'),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $business->members()->attach($user, ['role' => $role, 'status' => 'active', 'joined_at' => now()]);

    return [$business, $user];
}

it('renders the WhatsApp settings page without exposing provider secrets', function (): void {
    [$business, $owner] = whatsappSettingsOwner();
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(),
        'phone_number_id' => '1234567890',
        'business_account_id' => '9876543210',
        'access_token' => 'secret-access-token',
        'status' => 'connected',
        'connected_at' => now(),
    ]);
    config(['whatsapp.api_version' => 'v27.0']);

    $this->actingAs($owner)->get(route('whatsapp.connection.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('whatsapp/connection')
            ->where('canConfigure', true)
            ->where('canDisconnect', true)
            ->where('apiVersionConfigured', true)
            ->where('connection.phone_number_id', '1234567890')
            ->missing('connection.access_token'));
});

it('verifies the phone and business account with Meta before saving credentials', function (): void {
    [$business, $owner] = whatsappSettingsOwner();
    config(['whatsapp.api_version' => 'v27.0']);
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/v27.0/1234567890*' => Http::response([
            'id' => '1234567890', 'display_phone_number' => '+1 555 0100', 'verified_name' => 'Barber Shop',
        ]),
        'graph.facebook.com/v27.0/9876543210/phone_numbers*' => Http::response(['data' => [['id' => '1234567890']]]),
        'graph.facebook.com/v27.0/9876543210*' => Http::response(['id' => '9876543210']),
    ]);

    $this->actingAs($owner)->put(route('whatsapp.connection.store'), [
        'phone_number_id' => '1234567890',
        'business_account_id' => '9876543210',
        'access_token' => 'test-access-token',
    ])->assertRedirect();

    $connection = WhatsAppConnection::query()->where('business_id', $business->getKey())->firstOrFail();
    expect($connection->status)->toBe('connected')
        ->and($connection->access_token)->toBe('test-access-token')
        ->and($connection->metadata)->toMatchArray([
            'display_phone_number' => '+1 555 0100',
            'verified_name' => 'Barber Shop',
            'business_name' => null,
        ]);
    expect(DB::table('whatsapp_connections')->where('id', $connection->getKey())->value('access_token'))
        ->not->toBe('test-access-token');
    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'user_id' => $owner->getKey(),
        'action' => 'whatsapp.connected',
    ]);
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-access-token'));
});

it('rejects credentials Meta cannot verify and does not save or audit them', function (): void {
    [$business, $owner] = whatsappSettingsOwner();
    config(['whatsapp.api_version' => 'v27.0']);
    Http::preventStrayRequests();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 401)]);

    $this->actingAs($owner)->from(route('whatsapp.connection.show'))
        ->put(route('whatsapp.connection.store'), [
            'phone_number_id' => '1234567890',
            'business_account_id' => '9876543210',
            'access_token' => 'invalid-token',
        ])
        ->assertRedirect(route('whatsapp.connection.show'))
        ->assertSessionHasErrors('access_token');

    $this->assertDatabaseMissing('whatsapp_connections', ['business_id' => $business->getKey()]);
    $this->assertDatabaseMissing('audit_logs', ['business_id' => $business->getKey(), 'action' => 'whatsapp.connected']);
});

it('rejects a phone number that is not associated with the supplied WhatsApp business account', function (): void {
    [$business, $owner] = whatsappSettingsOwner();
    config(['whatsapp.api_version' => 'v27.0']);
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/v27.0/1234567890*' => Http::response(['id' => '1234567890']),
        'graph.facebook.com/v27.0/9876543210/phone_numbers*' => Http::response(['data' => [['id' => 'different-phone-id']]]),
        'graph.facebook.com/v27.0/9876543210*' => Http::response(['id' => '9876543210']),
    ]);

    $this->actingAs($owner)->from(route('whatsapp.connection.show'))
        ->put(route('whatsapp.connection.store'), [
            'phone_number_id' => '1234567890',
            'business_account_id' => '9876543210',
            'access_token' => 'token-for-another-account',
        ])
        ->assertSessionHasErrors('access_token');

    $this->assertDatabaseMissing('whatsapp_connections', ['business_id' => $business->getKey()]);
});

it('handles a Meta connection failure without exposing provider details or saving credentials', function (): void {
    [$business, $owner] = whatsappSettingsOwner();
    config(['whatsapp.api_version' => 'v27.0']);
    Http::preventStrayRequests();
    Http::fake(['graph.facebook.com/*' => Http::failedConnection()]);

    $response = $this->actingAs($owner)->from(route('whatsapp.connection.show'))
        ->put(route('whatsapp.connection.store'), [
            'phone_number_id' => '1234567890',
            'business_account_id' => '9876543210',
            'access_token' => 'secret-token-value',
        ]);

    $response->assertSessionHasErrors('access_token');
    expect($response->getSession()->get('errors')->getBag('default')->first('access_token'))
        ->toBe('Meta could not verify these WhatsApp credentials. Check the account IDs, token permissions, and configured Graph API version.');
    $this->assertDatabaseMissing('whatsapp_connections', ['business_id' => $business->getKey()]);
});

it('requires numeric Meta identifiers before making provider requests', function (): void {
    [, $owner] = whatsappSettingsOwner();
    Http::preventStrayRequests();

    $this->actingAs($owner)->from(route('whatsapp.connection.show'))
        ->put(route('whatsapp.connection.store'), [
            'phone_number_id' => 'https://attacker.example',
            'business_account_id' => '9876543210',
            'access_token' => 'test-access-token',
        ])
        ->assertSessionHasErrors('phone_number_id');
});

it('allows a view-only manager to inspect status but rejects configuration and disconnect actions', function (): void {
    [$business, $manager] = whatsappSettingsOwner('manager');
    Http::preventStrayRequests();

    $this->actingAs($manager)->get(route('whatsapp.connection.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('whatsapp/connection')
            ->where('canConfigure', false)
            ->where('canDisconnect', false));

    $this->actingAs($manager)->put(route('whatsapp.connection.store'), [
        'phone_number_id' => '1234567890',
        'business_account_id' => '9876543210',
        'access_token' => 'unauthorized-token',
    ])->assertForbidden();
    $this->actingAs($manager)->delete(route('whatsapp.connection.destroy'))->assertForbidden();
    $this->assertDatabaseMissing('whatsapp_connections', ['business_id' => $business->getKey()]);
});

it('clears the stored access token and audits a privileged disconnect', function (): void {
    [$business, $owner] = whatsappSettingsOwner();
    $connection = WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(),
        'phone_number_id' => '1234567890',
        'business_account_id' => '9876543210',
        'access_token' => 'secret-access-token',
        'status' => 'connected',
        'connected_at' => now(),
    ]);

    $this->actingAs($owner)->delete(route('whatsapp.connection.destroy'))->assertRedirect();

    expect($connection->fresh()->status)->toBe('disconnected')
        ->and($connection->fresh()->access_token)->toBeNull()
        ->and($connection->fresh()->connected_at)->toBeNull();
    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'user_id' => $owner->getKey(),
        'action' => 'whatsapp.disconnected',
    ]);
});
