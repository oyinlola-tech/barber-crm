<?php

use App\Enums\WhatsAppMessageFeature;
use App\Models\Business;
use App\Models\User;
use App\Services\Settings\Queries\GetWhatsAppMessageFeaturesQuery;
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

    $features = WhatsAppMessageFeature::defaults();
    $features[WhatsAppMessageFeature::Outbound->value] = false;
    $features[WhatsAppMessageFeature::ReviewRequests->value] = false;

    $this->actingAs($owner)
        ->put('/settings/messaging', ['opt_out_keywords' => [' no more ', 'STOP'], 'features' => $features])
        ->assertRedirect();

    expect(app(GetWhatsAppMessagingSettingsQuery::class)->execute((int) $business->getKey()))
        ->toContain('NO MORE')
        ->toContain('STOP')
        ->toContain('UNSUBSCRIBE');
    $this->assertDatabaseHas('business_settings', [
        'business_id' => $business->getKey(),
        'key' => 'whatsapp.opt_out_keywords',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'user_id' => $owner->getKey(),
        'action' => 'business.messaging_settings_updated',
    ]);
    expect(app(GetWhatsAppMessageFeaturesQuery::class)->execute((int) $business->getKey()))
        ->toMatchArray($features);
});

it('rejects customer messaging switches unless every known feature is a boolean', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = attachMessagingSettingsOwner($business);
    $features = WhatsAppMessageFeature::defaults();
    unset($features[WhatsAppMessageFeature::ManualReplies->value]);

    $this->actingAs($owner)
        ->put('/settings/messaging', ['opt_out_keywords' => ['STOP'], 'features' => $features])
        ->assertSessionHasErrors('features.manual_replies');

    $this->assertDatabaseMissing('business_settings', [
        'business_id' => $business->getKey(),
        'key' => 'whatsapp.message_features',
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
        ->put('/settings/messaging', ['opt_out_keywords' => []])
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
