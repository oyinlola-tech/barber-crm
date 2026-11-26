<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\ServiceCatalog\Commands\UpdateServiceCommand;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function serviceCatalogMember(Business $business, string $role): User
{
    $user = User::factory()->create();
    $business->members()->attach($user, [
        'role' => $role,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    return $user;
}

function confirmServiceCatalogMfa(User $user): void
{
    $user->forceFill([
        'two_factor_secret' => encrypt('service-catalog-test-secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();
}

it('renders the active service catalog for staff with the view permission', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create(['currency' => 'NGN']);
    $receptionist = serviceCatalogMember($business, BusinessRole::Receptionist->value);
    $active = Service::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Classic haircut',
        'description' => 'A standard haircut.',
        'duration_minutes' => 30,
        'price' => '5000.00',
        'is_active' => true,
    ]);
    Service::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Retired service',
        'duration_minutes' => 30,
        'price' => '1.00',
        'is_active' => false,
    ]);

    $this->actingAs($receptionist)->get('/services')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('services/index')
            ->where('currency', 'NGN')
            ->has('services', 2)
            ->where('services.0.id', $active->getKey())
            ->where('services.1.is_active', false)
            ->where('permissions.create', false));
});

it('creates and audits a service through the authorized catalog workflow', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $manager = serviceCatalogMember($business, BusinessRole::Manager->value);
    confirmServiceCatalogMfa($manager);

    $this->actingAs($manager)->post('/services', [
        'name' => 'Beard trim',
        'description' => 'Shape and finish.',
        'duration_minutes' => 20,
        'price' => '2500.50',
    ])->assertRedirect(route('services.index'));

    $service = Service::query()->where('business_id', $business->getKey())->firstOrFail();
    expect($service->name)->toBe('Beard trim')
        ->and($service->duration_minutes)->toBe(20)
        ->and((string) $service->price)->toBe('2500.50');

    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'user_id' => $manager->getKey(),
        'action' => 'service.created',
        'auditable_id' => $service->getKey(),
    ]);
});

it('enforces service catalog permissions on both reads and writes', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $barber = serviceCatalogMember($business, BusinessRole::Barber->value);
    $viewer = serviceCatalogMember($business, BusinessRole::Viewer->value);
    $existing = Service::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Existing service',
        'duration_minutes' => 30,
        'price' => '1000.00',
        'is_active' => true,
    ]);

    $this->actingAs($barber)->get('/services')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('permissions.create', false));
    $this->actingAs($barber)->post('/services', [
        'name' => 'Unauthorized service',
        'duration_minutes' => 30,
        'price' => '1000.00',
    ])->assertForbidden();

    $this->actingAs($viewer)->get('/services')->assertForbidden();
    $this->assertDatabaseMissing('services', [
        'business_id' => $business->getKey(),
        'name' => 'Unauthorized service',
    ]);
    expect($existing->exists)->toBeTrue();
});

it('lists only services belonging to the configured barbershop', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create(['is_active' => false]);
    $owner = serviceCatalogMember($business, BusinessRole::Owner->value);
    confirmServiceCatalogMfa($owner);
    Service::query()->create([
        'business_id' => $otherBusiness->getKey(),
        'name' => 'Other barbershop service',
        'duration_minutes' => 30,
        'price' => '1000.00',
        'is_active' => true,
    ]);

    $this->actingAs($owner)->get('/services')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('services/index')
            ->has('services', 0));
});

it('updates and deactivates a service with a dedicated permission and audit record', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $manager = serviceCatalogMember($business, BusinessRole::Manager->value);
    confirmServiceCatalogMfa($manager);
    $service = Service::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Classic haircut',
        'duration_minutes' => 30,
        'price' => '5000.00',
        'is_active' => true,
    ]);

    $this->actingAs($manager)->put('/services/'.$service->getKey(), [
        'name' => 'Haircut and beard trim',
        'description' => 'Updated combined service.',
        'duration_minutes' => 45,
        'price' => '7500.25',
        'is_active' => false,
    ])->assertRedirect(route('services.index'));

    expect($service->refresh()->name)->toBe('Haircut and beard trim')
        ->and($service->duration_minutes)->toBe(45)
        ->and((string) $service->price)->toBe('7500.25')
        ->and($service->is_active)->toBeFalse();
    $this->assertDatabaseHas('audit_logs', [
        'business_id' => $business->getKey(),
        'user_id' => $manager->getKey(),
        'action' => 'service.updated',
        'auditable_id' => $service->getKey(),
    ]);
});

it('enforces service update permissions inside the command and scopes writes to the business', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create(['is_active' => false]);
    $barber = serviceCatalogMember($business, BusinessRole::Barber->value);
    $service = Service::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Protected service',
        'duration_minutes' => 30,
        'price' => '5000.00',
        'is_active' => true,
    ]);

    expect(fn () => app(UpdateServiceCommand::class)->execute(
        (int) $business->getKey(),
        (int) $service->getKey(),
        ['name' => 'Changed by unauthorized staff'],
        (int) $barber->getKey(),
    ))->toThrow(AuthorizationException::class);

    $foreignService = Service::query()->create([
        'business_id' => $otherBusiness->getKey(),
        'name' => 'Foreign service',
        'duration_minutes' => 30,
        'price' => '5000.00',
        'is_active' => true,
    ]);
    $manager = serviceCatalogMember($business, BusinessRole::Manager->value);
    confirmServiceCatalogMfa($manager);

    $this->actingAs($manager)->put('/services/'.$foreignService->getKey(), [
        'name' => 'Changed across businesses',
        'duration_minutes' => 30,
        'price' => '5000.00',
        'is_active' => true,
    ])->assertNotFound();

    expect($service->refresh()->name)->toBe('Protected service')
        ->and($foreignService->refresh()->name)->toBe('Foreign service');
});

it('adds catalog permissions to installed built-in roles without replacing existing grants', function (): void {
    $manager = Role::query()->create([
        'business_id' => null,
        'name' => 'Manager',
        'slug' => 'manager',
        'permissions' => ['dashboard.view', 'customers.view'],
    ]);
    $barber = Role::query()->create([
        'business_id' => null,
        'name' => 'Barber',
        'slug' => 'barber',
        'permissions' => ['visits.create'],
    ]);
    $migration = require database_path('migrations/2026_11_26_160000_add_service_catalog_role_permissions.php');

    $migration->up();

    expect($manager->fresh()->permissions)->toBe([
        'dashboard.view', 'customers.view', 'services.view', 'services.create',
    ])->and($barber->fresh()->permissions)->toBe(['visits.create', 'services.view']);

    $migration->down();

    expect($manager->fresh()->permissions)->toBe(['dashboard.view', 'customers.view'])
        ->and($barber->fresh()->permissions)->toBe(['visits.create']);
});

it('adds service update permission to the installed manager role without replacing grants', function (): void {
    $manager = Role::query()->create([
        'business_id' => null,
        'name' => 'Manager',
        'slug' => 'manager',
        'permissions' => ['dashboard.view', 'services.view', 'services.create'],
    ]);
    $migration = require database_path('migrations/2026_11_26_161000_add_service_update_permission.php');

    $migration->up();
    expect($manager->fresh()->permissions)->toBe(['dashboard.view', 'services.view', 'services.create', 'services.update']);

    $migration->down();
    expect($manager->fresh()->permissions)->toBe(['dashboard.view', 'services.view', 'services.create']);
});
