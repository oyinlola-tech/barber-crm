<?php

use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lets automation viewers inspect rules without exposing templates or mutation controls', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);
    Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Automation reader',
        'slug' => 'automation_reader',
        'permissions' => ['automations.view'],
    ]);
    $business->members()->attach($user, [
        'role' => 'automation_reader',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'provider_template_id' => 'provider-template-1',
        'name' => 'private_template_name',
        'language' => 'en',
        'category' => 'utility',
        'purpose' => 'experience',
        'status' => 'approved',
        'provider_status' => 'APPROVED',
        'is_enabled' => true,
        'components' => [['type' => 'BODY', 'text' => 'Thanks for visiting.']],
        'variable_map' => [],
    ]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Visit follow-up',
        'trigger' => 'visit_completed',
        'action' => 'send_template',
        'delay_minutes' => 4320,
        'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey()],
    ]);

    $this->actingAs($user)->get('/automations')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('automations/index')
        ->has('rules', 1)
        ->where('rules.0.id', $rule->getKey())
        ->has('templates', 0)
        ->where('capabilities.create', false)
        ->where('capabilities.update', false));
});
