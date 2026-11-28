<?php

use App\Enums\WhatsAppMessageFeature;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use App\Models\User;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function returnIntentionOwner(): User
{
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('return-intention-attendance-test-secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

it('records an arrived customer as a completed visit from the customer return date', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = returnIntentionOwner();
    $customer = Customer::factory()->create([
        'business_id' => $business->getKey(),
        'intended_return_date' => now()->toDateString(),
        'intended_return_status' => 'expected',
    ]);
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($owner)->put(route('customers.return-intention-status.update', $customer), ['status' => 'arrived'])
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->refresh()->intended_return_status)->toBe('arrived')
        ->and($customer->visits()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'customer.return_intention_status_updated')->exists())->toBeTrue();
});

it('marks a passed customer return date as missed without creating an appointment or visit', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = returnIntentionOwner();
    $customer = Customer::factory()->create([
        'business_id' => $business->getKey(),
        'intended_return_date' => now()->subDay()->toDateString(),
        'intended_return_status' => 'expected',
    ]);
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($owner)->put(route('customers.return-intention-status.update', $customer), ['status' => 'missed'])
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->refresh()->intended_return_status)->toBe('missed')
        ->and($customer->visits()->count())->toBe(0);
});

it('schedules a missed return follow-up through its enabled consent-checked rule', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = returnIntentionOwner();
    $customer = Customer::factory()->create([
        'business_id' => $business->getKey(),
        'intended_return_date' => now()->subDay()->toDateString(),
        'intended_return_status' => 'expected',
    ]);
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'granted' => true,
        'source' => 'written',
        'recorded_at' => now()->subDays(10),
    ]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'provider_template_id' => 'return-missed-template',
        'name' => 'missed_return',
        'language' => 'en',
        'category' => 'marketing',
        'purpose' => 'return_intention',
        'status' => 'synchronized',
        'provider_status' => 'APPROVED',
        'is_enabled' => true,
        'components' => [['type' => 'BODY', 'text' => 'We hope to see you again.']],
        'variable_map' => [],
    ]);
    AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Missed return follow-up',
        'trigger' => 'return_date_missed',
        'action' => 'send_template',
        'delay_minutes' => 60,
        'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey(), 'max_attempts' => 1],
    ]);

    $this->actingAs($owner)->put(route('customers.return-intention-status.update', $customer), ['status' => 'missed'])
        ->assertRedirect(route('customers.show', $customer));

    $scheduled = ScheduledMessage::query()->where('customer_id', $customer->getKey())->firstOrFail();
    expect($scheduled->status)->toBe('pending')
        ->and($scheduled->payload['trigger'])->toBe('return_date_missed')
        ->and($scheduled->payload['return_intention_date'])->toBe(now()->subDay()->toDateString());

    $features = WhatsAppMessageFeature::defaults();
    $features[WhatsAppMessageFeature::MissedVisitFollowUp->value] = false;
    $this->actingAs($owner)->put('/settings/messaging', [
        'opt_out_keywords' => ['STOP'],
        'features' => $features,
    ])->assertRedirect();

    expect($scheduled->refresh()->status)->toBe('cancelled');
});

it('cancels a missed return follow-up when its WhatsApp feature is disabled', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = returnIntentionOwner();
    $customer = Customer::factory()->create([
        'business_id' => $business->getKey(),
        'intended_return_date' => now()->subDay()->toDateString(),
        'intended_return_status' => 'expected',
    ]);
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'granted' => true,
        'source' => 'written',
        'recorded_at' => now()->subDays(10),
    ]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'provider_template_id' => 'disabled-missed-template',
        'name' => 'missed_return_disabled',
        'language' => 'en',
        'category' => 'marketing',
        'purpose' => 'return_intention',
        'status' => 'synchronized',
        'provider_status' => 'APPROVED',
        'is_enabled' => true,
        'components' => [['type' => 'BODY', 'text' => 'We hope to see you again.']],
        'variable_map' => [],
    ]);
    AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Missed return follow-up',
        'trigger' => 'return_date_missed',
        'action' => 'send_template',
        'delay_minutes' => 60,
        'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey(), 'max_attempts' => 1],
    ]);
    $features = WhatsAppMessageFeature::defaults();
    $features[WhatsAppMessageFeature::MissedVisitFollowUp->value] = false;
    BusinessSetting::query()->create([
        'business_id' => $business->getKey(),
        'key' => 'whatsapp.message_features',
        'value' => $features,
    ]);

    $this->actingAs($owner)->put(route('customers.return-intention-status.update', $customer), ['status' => 'missed'])
        ->assertRedirect(route('customers.show', $customer));

    expect(ScheduledMessage::query()->where('customer_id', $customer->getKey())->value('status'))->toBe('cancelled');
});

it('does not let staff mark a future return date as missed', function (): void {
    $this->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create();
    $owner = returnIntentionOwner();
    $customer = Customer::factory()->create([
        'business_id' => $business->getKey(),
        'intended_return_date' => now()->addDay()->toDateString(),
        'intended_return_status' => 'expected',
    ]);
    $business->members()->attach($owner, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($owner)->put(route('customers.return-intention-status.update', $customer), ['status' => 'missed'])
        ->assertSessionHasErrors('status');

    expect($customer->refresh()->intended_return_status)->toBe('expected')
        ->and($customer->visits()->count())->toBe(0);
});
