<?php

use App\Dtos\WhatsApp\ConnectWhatsAppDto;
use App\Models\Business;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Services\Businesses\Commands\UpdateBusinessCommand;
use App\Services\WhatsApp\Commands\ConnectWhatsAppCommand;
use App\Services\WhatsApp\Commands\DisconnectWhatsAppCommand;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function businessWriteMember(string $role): array
{
    test()->seed(BusinessRoleSeeder::class);
    $business = Business::factory()->create(['is_active' => true]);
    $actor = User::factory()->create();
    $business->members()->attach($actor, [
        'role' => $role,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    return [$business, $actor];
}

it('enforces business profile permissions inside its CQRS command', function (): void {
    [$business, $manager] = businessWriteMember('manager');
    [$ownerBusiness, $owner] = businessWriteMember('owner');

    expect(fn () => app(UpdateBusinessCommand::class)->execute(
        (int) $business->getKey(),
        (int) $manager->getKey(),
        ['name' => 'Unauthorized update'],
    ))->toThrow(HttpException::class);

    expect(fn () => app(UpdateBusinessCommand::class)->execute(
        (int) $ownerBusiness->getKey(),
        (int) $owner->getKey(),
        ['is_active' => false],
    ))->toThrow(ValidationException::class);

    expect($business->fresh()->name)->not->toBe('Unauthorized update')
        ->and($ownerBusiness->fresh()->is_active)->toBeTrue();

    $updated = app(UpdateBusinessCommand::class)->execute(
        (int) $ownerBusiness->getKey(),
        (int) $owner->getKey(),
        ['name' => 'New shop name'],
    );
    expect($updated->name)->toBe('New shop name');
});

it('blocks WhatsApp connect and disconnect commands without their permissions', function (): void {
    [$business, $manager] = businessWriteMember('manager');
    Http::preventStrayRequests();

    expect(fn () => app(ConnectWhatsAppCommand::class)->execute(
        (int) $business->getKey(),
        (int) $manager->getKey(),
        new ConnectWhatsAppDto('1234567890', '9876543210', 'must-not-be-sent'),
    ))->toThrow(HttpException::class);

    $connection = WhatsAppConnection::query()->create([
        'business_id' => $business->getKey(),
        'phone_number_id' => '1234567890',
        'business_account_id' => '9876543210',
        'access_token' => 'existing-token',
        'status' => 'connected',
    ]);

    expect(fn () => app(DisconnectWhatsAppCommand::class)->execute(
        (int) $business->getKey(),
        (int) $manager->getKey(),
    ))->toThrow(HttpException::class);

    expect($connection->fresh()->status)->toBe('connected')
        ->and($connection->fresh()->access_token)->toBe('existing-token');
    $this->assertDatabaseMissing('audit_logs', ['business_id' => $business->getKey()]);
    Http::assertNothingSent();
});
