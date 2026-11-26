<?php

use App\Models\Business;
use App\Models\User;
use App\Services\Settings\Queries\GetWhatsAppMessagingSettingsQuery;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;

uses(RefreshDatabase::class);

function attachMessagingSettingsOwner(Business $business): User
{
    $owner = User::factory()->create();
    $owner->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt('[]'),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $business->members()->attach($owner, [
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    return $owner;
}

it('uses configured defaults until the owner saves opt-out keywords', function (): void {
    $business = Business::factory()->create();

    expect(app(GetWhatsAppMessagingSettingsQuery::class)->execute((int) $business->getKey()))
        ->toContain('STOP')
        ->toContain('UNSUBSCRIBE');
});

it('allows the owner to save normalized messaging keywords and audits the change', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = attachMessagingSettingsOwner($business);

    $this->actingAs($owner)
        ->put('/settings/messaging', ['opt_out_keywords' => [' no more ', 'STOP']])
        ->assertRedirect();

    expect(app(GetWhatsAppMessagingSettingsQuery::class)->execute((int) $business->getKey()))
        ->toBe(['NO MORE', 'STOP']);
    $this->assertDatabaseHas('business_settings', [
        'business_id' => $business->getKey(),
        'key' => 'whatsapp.opt_out_keywords',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'user_id' => $owner->getKey(),
        'action' => 'business.messaging_settings_updated',
    ]);
});

it('rejects staff without settings permission and invalid or duplicate keywords', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $staff = User::factory()->create();
    $business->members()->attach($staff, [
        'role' => 'receptionist',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs($staff)
        ->put('/settings/messaging', ['opt_out_keywords' => ['NO MORE']])
        ->assertForbidden();

    $owner = attachMessagingSettingsOwner($business);
    $this->actingAs($owner)
        ->put('/settings/messaging', ['opt_out_keywords' => ['STOP', ' stop ']])
        ->assertSessionHasErrors('opt_out_keywords.1');

    $this->assertDatabaseMissing('business_settings', [
        'business_id' => $business->getKey(),
        'key' => 'whatsapp.opt_out_keywords',
    ]);
});
