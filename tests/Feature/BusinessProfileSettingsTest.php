<?php

use App\Models\Business;
use App\Models\User;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Fortify;

uses(RefreshDatabase::class);

function businessProfileSettingsOwner(): array
{
    test()->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
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

    return [$business, $owner];
}

it('stores and replaces a public business logo while persisting approved profile details', function (): void {
    [$business, $owner] = businessProfileSettingsOwner();
    Storage::fake('local');

    $this->from('/settings/business')->actingAs($owner)->put('/settings/business', [
        'name' => 'Barber House',
        'phone' => '+2348012345678',
        'email' => 'hello@example.test',
        'description' => 'A neighborhood barbershop.',
        'address' => '12 Main Street, Lagos',
        'public_review_url' => 'https://reviews.example.test/barber-house',
        'timezone' => 'Africa/Lagos',
        'currency' => 'NGN',
        'logo' => UploadedFile::fake()->image('barber-logo.png', 64, 64),
    ])->assertRedirect('/settings/business');

    $business->refresh();
    $firstLogoPath = $business->logo_path;
    expect($firstLogoPath)->toBeString()
        ->and($business->description)->toBe('A neighborhood barbershop.')
        ->and($business->address)->toBe('12 Main Street, Lagos')
        ->and($business->public_review_url)->toBe('https://reviews.example.test/barber-house');
    Storage::disk('local')->assertExists($firstLogoPath);
    $logoResponse = $this->actingAs($owner)->get('/settings/business/logo');
    $logoResponse
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
    expect($logoResponse->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');
    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'action' => 'business.settings_updated',
    ]);

    $this->from('/settings/business')->actingAs($owner)->put('/settings/business', [
        'name' => 'Barber House',
        'phone' => '+2348012345678',
        'email' => 'hello@example.test',
        'description' => 'A neighborhood barbershop.',
        'address' => '12 Main Street, Lagos',
        'public_review_url' => 'https://reviews.example.test/barber-house',
        'timezone' => 'Africa/Lagos',
        'currency' => 'NGN',
        'logo' => UploadedFile::fake()->image('replacement.png', 64, 64),
    ])->assertRedirect('/settings/business');

    $replacementPath = $business->fresh()->logo_path;
    expect($replacementPath)->not->toBe($firstLogoPath);
    Storage::disk('local')->assertMissing($firstLogoPath);
    Storage::disk('local')->assertExists($replacementPath);

    $this->from('/settings/business')->actingAs($owner)->put('/settings/business', [
        'name' => 'Barber House',
        'phone' => '+2348012345678',
        'email' => 'hello@example.test',
        'description' => 'A neighborhood barbershop.',
        'address' => '12 Main Street, Lagos',
        'public_review_url' => 'https://reviews.example.test/barber-house',
        'timezone' => 'Africa/Lagos',
        'currency' => 'NGN',
        'remove_logo' => true,
    ])->assertRedirect('/settings/business');

    expect($business->fresh()->logo_path)->toBeNull();
    Storage::disk('local')->assertMissing($replacementPath);
});

it('rejects non-HTTPS public review links and unsafe logo formats', function (): void {
    [, $owner] = businessProfileSettingsOwner();
    Storage::fake('local');

    $response = $this->actingAs($owner)->from('/settings/business')->put('/settings/business', [
        'name' => 'Barber House',
        'phone' => null,
        'email' => null,
        'description' => null,
        'address' => null,
        'public_review_url' => 'http://reviews.example.test/barber-house',
        'timezone' => 'Africa/Lagos',
        'currency' => 'NGN',
        'logo' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
    ]);

    $response->assertSessionHasErrors(['public_review_url', 'logo']);
    expect(Storage::disk('local')->allFiles('businesses'))->toBeEmpty();
});

it('requires settings view permission before serving the private business logo', function (): void {
    [$business, $owner] = businessProfileSettingsOwner();
    $viewer = User::factory()->create();
    $business->members()->attach($viewer, [
        'role' => 'viewer',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    Storage::fake('local');
    $path = 'businesses/'.$business->getKey().'/logo/private.png';
    Storage::disk('local')->put($path, 'private image bytes');
    $business->update(['logo_path' => $path]);

    $this->actingAs($viewer)->get('/settings/business/logo')->assertForbidden();
    $this->actingAs($owner)->get('/settings/business/logo')->assertOk();

    $outsideLogoPath = 'businesses/'.$business->getKey().'/other.png';
    Storage::disk('local')->put($outsideLogoPath, 'must not be exposed');
    $business->update(['logo_path' => 'businesses/'.$business->getKey().'/logo/../other.png']);
    $this->actingAs($owner)->get('/settings/business/logo')->assertNotFound();
    Storage::disk('local')->assertExists($outsideLogoPath);
});
