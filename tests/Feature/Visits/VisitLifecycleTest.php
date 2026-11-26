<?php

use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerConsent;
use App\Models\MessageTemplate;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function visitLifecycleOwner(): User
{
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('visit-lifecycle-test-secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

function visitLifecycleSetup(string $role = 'owner'): array
{
    $business = Business::factory()->create();
    $user = visitLifecycleOwner();
    $customer = Customer::factory()->create(['business_id' => $business->getKey()]);
    $business->members()->attach($user, ['role' => $role, 'status' => 'active', 'joined_at' => now()]);

    return [$business, $user, $customer];
}

it('corrects a completed visit and audits the revision without exposing notes', function (): void {
    [$business, $user, $customer] = visitLifecycleSetup();
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'recorded_by' => $user->getKey(),
        'visited_at' => now()->subDays(8),
        'notes' => 'Old note',
    ]);

    $this->actingAs($user)->put(route('visits.update', $visit), [
        'visited_at' => now()->subDays(7)->format('Y-m-d H:i:s'),
        'notes' => 'Corrected note',
        'service_ids' => [],
    ])->assertRedirect(route('customers.show', $customer));

    expect($visit->refresh()->visited_at->toDateString())->toBe(now()->subDays(7)->toDateString())
        ->and((int) $visit->automation_revision)->toBe(2)
        ->and($visit->notes)->toBe('Corrected note')
        ->and(AuditLog::query()->where('action', 'visit.updated')->firstOrFail()->after)
        ->not->toHaveKey('notes');
});

it('stores staff-entered visit times using the barbershop timezone', function (): void {
    [$business, $user, $customer] = visitLifecycleSetup();
    $business->update(['timezone' => 'Africa/Lagos']);
    $localVisitTime = now('Africa/Lagos')->subDays(2)->setTime(12, 30)->format('Y-m-d\\TH:i');

    $this->actingAs($user)->post(route('visits.store'), [
        'customer_id' => $customer->getKey(),
        'visited_at' => $localVisitTime,
        'service_ids' => [],
    ])->assertRedirect(route('customers.show', $customer));

    $visit = Visit::query()->where('customer_id', $customer->getKey())->firstOrFail();
    $expectedUtc = CarbonImmutable::parse($localVisitTime, 'Africa/Lagos')->utc();

    expect($visit->visited_at->toIso8601String())->toBe($expectedUtc->toIso8601String());
});

it('cancels the old follow-up generation and schedules a corrected visit generation', function (): void {
    [$business, $user, $customer] = visitLifecycleSetup();
    CustomerConsent::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'granted' => true,
        'source' => 'in_person',
        'recorded_at' => now(),
    ]);
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(),
        'provider_template_id' => 'provider-template',
        'name' => 'visit_follow_up',
        'language' => 'en',
        'category' => 'utility',
        'purpose' => 'thank_you',
        'status' => 'synchronized',
        'provider_status' => 'APPROVED',
        'is_enabled' => true,
        'components' => [['type' => 'BODY', 'text' => 'Thanks for visiting.']],
        'variable_map' => [],
    ]);
    $rule = AutomationRule::query()->create([
        'business_id' => $business->getKey(),
        'name' => 'Visit follow up',
        'trigger' => 'visit_completed',
        'action' => 'send_template',
        'delay_minutes' => 60,
        'is_enabled' => true,
        'settings' => ['template_id' => $template->getKey()],
    ]);
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'recorded_by' => $user->getKey(),
        'visited_at' => now()->subDay(),
    ]);
    $oldFollowUp = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'automation_rule_id' => $rule->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'visit:'.$visit->getKey().':revision:1:rule:'.$rule->getKey().':step:1',
        'payload' => ['visit_id' => $visit->getKey(), 'visit_revision' => 1],
        'status' => 'pending',
        'scheduled_at' => now()->addHour(),
    ]);

    $this->actingAs($user)->put(route('visits.update', $visit), [
        'visited_at' => now()->subHours(12)->format('Y-m-d H:i:s'),
        'service_ids' => [],
    ])->assertRedirect(route('customers.show', $customer));

    $newFollowUp = ScheduledMessage::query()
        ->where('business_id', $business->getKey())
        ->where('idempotency_key', 'visit:'.$visit->getKey().':revision:2:rule:'.$rule->getKey().':step:1')
        ->firstOrFail();

    expect($oldFollowUp->refresh()->status)->toBe('cancelled')
        ->and($newFollowUp->status)->toBe('pending')
        ->and($newFollowUp->payload['visit_revision'])->toBe(2);
});

it('voids a visit reversibly, cancels its pending follow-ups, and preserves its audit trail', function (): void {
    [$business, $user, $customer] = visitLifecycleSetup();
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'recorded_by' => $user->getKey(),
        'visited_at' => now()->subDay(),
    ]);
    $scheduled = ScheduledMessage::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'channel' => 'whatsapp',
        'idempotency_key' => 'visit:'.$visit->getKey().':revision:1:rule:2:step:1',
        'payload' => ['visit_id' => $visit->getKey()],
        'status' => 'pending',
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($user)->delete(route('visits.destroy', $visit))
        ->assertRedirect(route('customers.show', $customer));

    expect(Visit::query()->find($visit->getKey()))->toBeNull()
        ->and(Visit::withTrashed()->findOrFail($visit->getKey())->trashed())->toBeTrue()
        ->and($scheduled->refresh()->status)->toBe('cancelled')
        ->and($customer->fresh()->visits()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'visit.voided')->exists())->toBeTrue();
});

it('restores a voided visit only when the actor has the dedicated permission', function (): void {
    [$business, $user, $customer] = visitLifecycleSetup();
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'recorded_by' => $user->getKey(),
        'visited_at' => now()->subDay(),
    ]);
    $visit->delete();

    $this->actingAs($user)->post(route('visits.restore', $visit))
        ->assertRedirect(route('customers.show', $customer));

    expect(Visit::query()->find($visit->getKey()))->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'visit.restored')->exists())->toBeTrue();
});

it('rejects visit corrections from staff without the update permission', function (): void {
    [$business, $user, $customer] = visitLifecycleSetup('viewer');
    $visit = Visit::query()->create([
        'business_id' => $business->getKey(),
        'customer_id' => $customer->getKey(),
        'recorded_by' => $user->getKey(),
        'visited_at' => now()->subDay(),
    ]);

    $this->actingAs($user)->put(route('visits.update', $visit), [
        'visited_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
        'service_ids' => [],
    ])->assertForbidden();

    expect($visit->refresh()->automation_revision)->toBe(1);
});

it('hides visits belonging to a different active barbershop', function (): void {
    [$business, $user] = visitLifecycleSetup();
    $otherBusiness = Business::factory()->create(['is_active' => false]);
    $otherCustomer = Customer::factory()->create(['business_id' => $otherBusiness->getKey()]);
    $visit = Visit::query()->create([
        'business_id' => $otherBusiness->getKey(),
        'customer_id' => $otherCustomer->getKey(),
        'recorded_by' => $user->getKey(),
        'visited_at' => now()->subDay(),
    ]);

    $this->actingAs($user)->put(route('visits.update', $visit), [
        'visited_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
        'service_ids' => [],
    ])->assertForbidden();
});
