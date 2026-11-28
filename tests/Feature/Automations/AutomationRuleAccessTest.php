<?php

use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\ScheduledMessage;
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

it('allows authorized staff to delete an automation and cancels its pending messages', function (): void {
    $business = Business::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);
    Role::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Automation manager',
        'slug' => 'automation_manager',
        'permissions' => ['automations.delete'],
    ]);
    $business->members()->attach($user, [
        'role' => 'automation_manager',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Visit follow-up',
        'trigger' => 'visit_completed',
        'action' => 'send_template',
        'delay_minutes' => 4320,
        'is_enabled' => true,
        'settings' => [],
    ]);
    $scheduledMessage = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'automation_rule_id' => $rule->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'delete-automation-test',
        'status' => 'pending',
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($user)->delete(route('automations.destroy', $rule))->assertRedirect(route('automations.index'));

    expect(AutomationRule::query()->whereKey($rule->getKey())->exists())->toBeFalse()
        ->and($scheduledMessage->refresh()->status)->toBe('cancelled')
        ->and(AuditLog::query()->where('action', 'automation_rule.deleted')->where('auditable_id', $rule->getKey())->exists())->toBeTrue();
});

it('rejects automation deletion without the delete permission', function (): void {
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
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Protected rule',
        'trigger' => 'visit_completed',
        'action' => 'send_template',
        'delay_minutes' => 4320,
        'is_enabled' => true,
        'settings' => [],
    ]);

    $this->actingAs($user)->delete(route('automations.destroy', $rule))->assertForbidden();

    expect(AutomationRule::query()->whereKey($rule->getKey())->exists())->toBeTrue();
});
