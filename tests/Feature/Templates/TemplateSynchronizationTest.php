<?php

use App\Models\Business;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Services\Templates\Commands\SynchronizeMessageTemplatesCommand;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function templateSyncActor(): array
{
    test()->seed(BusinessRoleSeeder::class);
    config(['whatsapp.api_version' => 'v21.0']);
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
    WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(),
        'business_account_id' => 'business-account-1',
        'phone_number_id' => 'phone-number-1',
        'access_token' => 'secret-test-token',
        'status' => 'connected',
        'connected_at' => now(),
    ]);

    return [$business, $user];
}

function providerTemplate(string $id, string $name, string $status = 'APPROVED'): array
{
    return [
        'id' => $id,
        'name' => $name,
        'language' => 'en',
        'status' => $status,
        'category' => 'UTILITY',
        'components' => [['type' => 'BODY', 'text' => 'Thanks for visiting.']],
    ];
}

it('disables templates missing from Meta and synchronizes rejected provider states', function (): void {
    [$business, $user] = templateSyncActor();
    $deletedTemplate = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'provider_template_id' => 'meta-deleted',
        'name' => 'deleted_template', 'language' => 'en', 'category' => 'utility',
        'purpose' => 'thank_you', 'status' => 'synchronized', 'provider_status' => 'APPROVED',
        'is_enabled' => true, 'components' => [['type' => 'BODY', 'text' => 'Old copy']], 'variable_map' => [],
    ]);
    $rejectedTemplate = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'provider_template_id' => 'meta-rejected',
        'name' => 'rejected_template', 'language' => 'en', 'category' => 'utility',
        'purpose' => 'thank_you', 'status' => 'synchronized', 'provider_status' => 'APPROVED',
        'is_enabled' => true, 'components' => [['type' => 'BODY', 'text' => 'Old copy']], 'variable_map' => [],
    ]);
    Http::fake(['graph.facebook.com/*' => Http::response([
        'data' => [providerTemplate('meta-rejected', 'rejected_template', 'REJECTED')],
    ])]);

    expect(app(SynchronizeMessageTemplatesCommand::class)->execute((int) $business->getKey(), (int) $user->getKey()))->toBe(1)
        ->and($deletedTemplate->fresh()->status)->toBe('unavailable')
        ->and($deletedTemplate->fresh()->provider_status)->toBe('DELETED')
        ->and($deletedTemplate->fresh()->is_enabled)->toBeFalse()
        ->and($rejectedTemplate->fresh()->provider_status)->toBe('REJECTED')
        ->and($rejectedTemplate->fresh()->is_enabled)->toBeFalse();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/business-account-1/message_templates')
        && $request->hasHeader('Authorization', 'Bearer secret-test-token'));
});

it('safely restores a previously missing template as disabled when Meta returns it', function (): void {
    [$business, $user] = templateSyncActor();
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'provider_template_id' => 'meta-returned',
        'name' => 'returned_template', 'language' => 'en', 'category' => 'utility',
        'purpose' => 'thank_you', 'status' => 'unavailable', 'provider_status' => 'DELETED',
        'is_enabled' => false, 'components' => [], 'variable_map' => [],
    ]);
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => [providerTemplate('meta-returned', 'returned_template')]])]);

    app(SynchronizeMessageTemplatesCommand::class)->execute((int) $business->getKey(), (int) $user->getKey());

    expect($template->fresh()->status)->toBe('synchronized')
        ->and($template->fresh()->provider_status)->toBe('APPROVED')
        ->and($template->fresh()->is_enabled)->toBeFalse();
});

it('does not alter local template availability when provider pagination fails', function (): void {
    [$business, $user] = templateSyncActor();
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'provider_template_id' => 'meta-existing',
        'name' => 'existing_template', 'language' => 'en', 'category' => 'utility',
        'purpose' => 'thank_you', 'status' => 'synchronized', 'provider_status' => 'APPROVED',
        'is_enabled' => true, 'components' => [], 'variable_map' => [],
    ]);
    Http::fakeSequence()
        ->push(['data' => [], 'paging' => ['cursors' => ['after' => 'next-page']]])
        ->push(['error' => ['message' => 'temporary provider failure']], 503);

    expect(fn () => app(SynchronizeMessageTemplatesCommand::class)->execute((int) $business->getKey(), (int) $user->getKey()))
        ->toThrow(RequestException::class);

    expect($template->fresh()->status)->toBe('synchronized')
        ->and($template->fresh()->provider_status)->toBe('APPROVED')
        ->and($template->fresh()->is_enabled)->toBeTrue();
    $this->assertDatabaseMissing('audit_logs', ['action' => 'message_templates.synchronized']);
});

it('rejects a repeated provider pagination cursor without changing synchronized templates', function (): void {
    [$business, $user] = templateSyncActor();
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'provider_template_id' => 'meta-existing',
        'name' => 'existing_template', 'language' => 'en', 'category' => 'utility',
        'purpose' => 'thank_you', 'status' => 'synchronized', 'provider_status' => 'APPROVED',
        'is_enabled' => true, 'components' => [], 'variable_map' => [],
    ]);
    Http::fakeSequence()
        ->push(['data' => [], 'paging' => ['cursors' => ['after' => 'same-cursor']]])
        ->push(['data' => [], 'paging' => ['cursors' => ['after' => 'same-cursor']]]);

    expect(fn () => app(SynchronizeMessageTemplatesCommand::class)->execute((int) $business->getKey(), (int) $user->getKey()))
        ->toThrow(RuntimeException::class);

    expect($template->fresh()->status)->toBe('synchronized')
        ->and($template->fresh()->provider_status)->toBe('APPROVED')
        ->and($template->fresh()->is_enabled)->toBeTrue();
    $this->assertDatabaseMissing('audit_logs', ['action' => 'message_templates.synchronized']);
});

it('requires staff to re-enable a template rebound to a new provider identity', function (): void {
    [$business, $user] = templateSyncActor();
    $template = MessageTemplate::query()->create([
        'business_id' => $business->getKey(), 'provider_template_id' => 'meta-old-id',
        'name' => 'same_name', 'language' => 'en', 'category' => 'utility',
        'purpose' => 'thank_you', 'status' => 'synchronized', 'provider_status' => 'APPROVED',
        'is_enabled' => true, 'components' => [], 'variable_map' => [],
    ]);
    Http::fake(['graph.facebook.com/*' => Http::response(['data' => [providerTemplate('meta-new-id', 'same_name')]])]);

    app(SynchronizeMessageTemplatesCommand::class)->execute((int) $business->getKey(), (int) $user->getKey());

    expect($template->fresh()->provider_template_id)->toBe('meta-new-id')
        ->and($template->fresh()->is_enabled)->toBeFalse();
    $this->assertDatabaseCount('message_templates', 1);
});
