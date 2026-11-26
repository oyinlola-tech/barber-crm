<?php

use App\Models\Business;
use App\Models\MessageTemplate;
use App\Models\User;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function templateManagementOwner(): User
{
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $owner->forceFill([
        'two_factor_secret' => encrypt('template-management-test-secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $owner;
}

function templateManagementBusiness(User $owner): Business
{
    $business = Business::factory()->create(['is_active' => true]);
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    return $business;
}

it('renders only real template records and the configured supported variables', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $owner = templateManagementOwner();
    $business = templateManagementBusiness($owner);
    MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'visit_thanks',
        'language' => 'en',
        'category' => 'utility',
        'purpose' => 'thank_you',
        'status' => 'draft',
        'provider_status' => null,
        'provider_template_id' => null,
        'is_enabled' => false,
        'components' => [['type' => 'BODY', 'text' => 'Thank you, {{1}}', 'example' => ['body_text' => [['Alex']]]]],
        'variable_map' => ['customer_name'],
    ]);

    $this->actingAs($owner)->get(route('templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/index')
            ->has('templates', 1)
            ->where('templates.0.provider_status', null)
            ->where('templates.0.variable_map.0', 'customer_name')
            ->where('allowedVariables', config('automation.allowed_template_variables'))
            ->where('permissions.update', true));
});

it('allows an authorized user to edit a local draft through the existing update route', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $owner = templateManagementOwner();
    $business = templateManagementBusiness($owner);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'visit_thanks',
        'language' => 'en',
        'category' => 'utility',
        'purpose' => 'thank_you',
        'status' => 'draft',
        'provider_status' => null,
        'provider_template_id' => null,
        'is_enabled' => false,
        'components' => [['type' => 'BODY', 'text' => 'Thank you, {{1}}', 'example' => ['body_text' => [['Alex']]]]],
        'variable_map' => ['customer_name'],
    ]);

    $this->actingAs($owner)->put(route('templates.update', $template), [
        'name' => 'visit_follow_up',
        'language' => 'en',
        'category' => 'UTILITY',
        'purpose' => 'experience',
        'body' => 'How was your haircut, {{customer_name}}?',
        'examples' => ['customer_name' => 'Alex'],
    ])->assertRedirect();

    expect($template->refresh()->name)->toBe('visit_follow_up')
        ->and($template->components[0]['text'])->toBe('How was your haircut, {{1}}?')
        ->and($template->variable_map)->toBe(['customer_name']);
});

it('saves only supported positional variable mappings for provider templates', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $owner = templateManagementOwner();
    $business = templateManagementBusiness($owner);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'provider_template_id' => 'meta-template-1',
        'name' => 'visit_follow_up',
        'language' => 'en',
        'category' => 'utility',
        'purpose' => null,
        'status' => 'synchronized',
        'provider_status' => 'APPROVED',
        'is_enabled' => false,
        'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}, your last visit was {{2}}.']],
        'variable_map' => [],
    ]);

    $this->actingAs($owner)->patch(route('templates.variables', $template), [
        'variables' => ['customer_name', 'last_visit_date'],
    ])->assertRedirect();

    expect($template->refresh()->variable_map)->toBe(['customer_name', 'last_visit_date']);
});

it('rejects a provider variable mapping when any variable is unsupported', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $owner = templateManagementOwner();
    $business = templateManagementBusiness($owner);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'provider_template_id' => 'meta-template-2',
        'name' => 'visit_follow_up',
        'language' => 'en',
        'category' => 'utility',
        'status' => 'synchronized',
        'provider_status' => 'APPROVED',
        'is_enabled' => false,
        'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}.']],
        'variable_map' => [],
    ]);

    $this->actingAs($owner)->patch(route('templates.variables', $template), [
        'variables' => ['customer_address'],
    ])->assertSessionHasErrors('variables.0');

    expect($template->refresh()->variable_map)->toBe([]);
});
