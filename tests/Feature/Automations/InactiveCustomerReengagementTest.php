<?php

use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\MessageTemplate;
use App\Models\Role;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Models\Visit;
use App\Services\Automations\Commands\CreateAutomationRuleCommand;
use App\Services\Automations\Commands\ScheduleInactiveCustomerFollowUpsCommand;
use App\Services\Automations\Commands\SetAutomationRuleStatusCommand;
use App\Services\Automations\Commands\UpdateAutomationRuleCommand;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

function inactiveCustomerFixture(array $customerAttributes = []): array
{
    $business = Business::factory()->create(['timezone' => 'Africa/Lagos']);
    $customer = Customer::factory()->create([
        'business_id' => $business->getKey(),
        ...$customerAttributes,
    ]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'granted' => true,
        'source' => 'in_person',
        'recorded_at' => now()->subMonths(2),
    ]);
    Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'visited_at' => now('UTC')->subDays(45),
    ]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'provider_template_id' => 'meta-template-'.$customer->getKey(),
        'name' => 'come_back',
        'language' => 'en',
        'category' => 'marketing',
        'purpose' => 'reengagement',
        'status' => 'synchronized',
        'provider_status' => 'APPROVED',
        'is_enabled' => true,
        'components' => [['type' => 'BODY', 'text' => 'We would love to see you again.']],
        'variable_map' => [],
    ]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Re-engage inactive customers',
        'trigger' => 'customer_inactive',
        'action' => 'send_template',
        'delay_minutes' => 60,
        'is_enabled' => true,
        'settings' => [
            'template_id' => $template->getKey(),
            'inactive_after_days' => 30,
            'reengagement_cycle_days' => 30,
            'max_follow_ups' => 2,
            'repeat_interval_minutes' => 10080,
        ],
    ]);

    return compact('business', 'customer', 'template', 'rule');
}

it('schedules one idempotent inactive customer follow-up and respects a stated return date', function (): void {
    config(['automation.enabled' => true]);
    $eligible = inactiveCustomerFixture();
    $returning = inactiveCustomerFixture(['intended_return_date' => now('Africa/Lagos')->addDays(4)->toDateString()]);
    $command = app(ScheduleInactiveCustomerFollowUpsCommand::class);

    expect($command->execute())->toBe(1)
        ->and($command->execute())->toBe(0);

    $scheduled = ScheduledMessage::query()->where('customer_id', $eligible['customer']->getKey())->first();
    expect($scheduled)->not->toBeNull()
        ->and($scheduled->payload['trigger'])->toBe('customer_inactive')
        ->and($scheduled->payload['inactivity_anchor'])->not->toBeEmpty();
    $this->assertDatabaseCount('scheduled_messages', 1);
    $this->assertDatabaseMissing('scheduled_messages', ['customer_id' => $returning['customer']->getKey()]);
});

it('does not schedule re-engagement when automation is disabled or consent is withdrawn', function (): void {
    config(['automation.enabled' => false]);
    $fixture = inactiveCustomerFixture();
    $command = app(ScheduleInactiveCustomerFollowUpsCommand::class);

    expect($command->execute())->toBe(0);
    config(['automation.enabled' => true]);
    CustomerConsent::query()->create([
        'business_id' => $fixture['business']->getKey(),
        'customer_id' => $fixture['customer']->getKey(),
        'channel' => 'whatsapp',
        'granted' => false,
        'source' => 'customer_request',
        'recorded_at' => now(),
        'revoked_at' => now(),
    ]);

    expect($command->execute())->toBe(0);
    $this->assertDatabaseCount('scheduled_messages', 0);
});

it('does not schedule archived customers or customers that visited more recently', function (): void {
    config(['automation.enabled' => true]);
    $archived = inactiveCustomerFixture(['archived_at' => now()]);
    $recent = inactiveCustomerFixture();
    Visit::query()->create([
        'business_id' => $recent['business']->getKey(),
        'customer_id' => $recent['customer']->getKey(),
        'visited_at' => now('UTC')->subDays(2),
    ]);

    expect(app(ScheduleInactiveCustomerFollowUpsCommand::class)->execute())->toBe(0);
    $this->assertDatabaseCount('scheduled_messages', 0);
});

it('allows an authorized owner to edit the inactivity threshold and follow-up policy', function (): void {
    $fixture = inactiveCustomerFixture();
    $this->seed(BusinessRoleSeeder::class);
    $owner = User::factory()->create();
    $fixture['business']->members()->attach($owner, [
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $updated = app(UpdateAutomationRuleCommand::class)->execute(
        (int) $fixture['business']->getKey(),
        (int) $fixture['rule']->getKey(),
        (int) $owner->getKey(),
        [
            'name' => 'Six week re-engagement',
            'settings' => [
                'template_id' => $fixture['template']->getKey(),
                'inactive_after_days' => 42,
                'max_follow_ups' => 3,
                'repeat_interval_minutes' => 20160,
            ],
        ],
    );

    expect($updated->name)->toBe('Six week re-engagement')
        ->and($updated->settings['inactive_after_days'])->toBe(42)
        ->and($updated->settings['max_follow_ups'])->toBe(3)
        ->and($updated->settings['repeat_interval_minutes'])->toBe(20160);
});

it('rejects enabled automation rules with multiple follow-ups and no repeat interval', function (): void {
    $fixture = inactiveCustomerFixture();
    $this->seed(BusinessRoleSeeder::class);
    $owner = User::factory()->create();
    $fixture['business']->members()->attach($owner, [
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    expect(fn () => app(CreateAutomationRuleCommand::class)->execute(
        (int) $fixture['business']->getKey(),
        [
            'name' => 'No repeat delay',
            'trigger' => 'customer_inactive',
            'action' => 'send_template',
            'delay_minutes' => 0,
            'is_enabled' => true,
            'settings' => [
                'template_id' => $fixture['template']->getKey(),
                'inactive_after_days' => 30,
                'max_follow_ups' => 2,
                'repeat_interval_minutes' => 0,
            ],
        ],
        (int) $owner->getKey(),
    ))->toThrow(ValidationException::class);
});

it('uses separate enable and disable permissions when changing a rule status', function (): void {
    $fixture = inactiveCustomerFixture();
    $user = User::factory()->create();
    Role::query()->create([
        'business_id' => $fixture['business']->getKey(),
        'name' => 'Automation enabler',
        'slug' => 'automation_enabler',
        'permissions' => ['automations.enable'],
    ]);
    $fixture['business']->members()->attach($user, [
        'role' => 'automation_enabler',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $fixture['rule']->update(['is_enabled' => false]);
    $command = app(SetAutomationRuleStatusCommand::class);

    expect($command->execute((int) $fixture['business']->getKey(), (int) $fixture['rule']->getKey(), true, (int) $user->getKey())->is_enabled)->toBeTrue();
    expect(fn () => $command->execute((int) $fixture['business']->getKey(), (int) $fixture['rule']->getKey(), false, (int) $user->getKey()))
        ->toThrow(AuthorizationException::class);
});
