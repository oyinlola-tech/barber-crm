<?php

use App\Models\User;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('revokes only the resetting user sessions after a successful password reset', function (): void {
    $user = User::factory()->create(['password' => 'OldSecurePassword-123!']);
    $unrelated = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'reset-user-session-one', 'user_id' => $user->getKey(), 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'reset-user-session-two', 'user_id' => $user->getKey(), 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'unrelated-user-session', 'user_id' => $unrelated->getKey(), 'payload' => '', 'last_activity' => now()->timestamp],
    ]);
    app()->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
    {
        public function verify($data): bool
        {
            return true;
        }
    });
    $previousRememberToken = $user->remember_token;
    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'email' => $user->email,
        'token' => $token,
        'password' => 'NewSecurePassword-456!',
        'password_confirmation' => 'NewSecurePassword-456!',
    ])->assertRedirect();

    expect(Hash::check('NewSecurePassword-456!', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe($previousRememberToken)
        ->and(DB::table('sessions')->where('user_id', $user->getKey())->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'unrelated-user-session')->exists())->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
    $this->assertDatabaseHas('audit_logs', ['user_id' => $user->getKey(), 'action' => 'account.password_reset']);
});

it('changes the password only after current-password validation and revokes remembered sessions', function (): void {
    config(['session.driver' => 'database']);
    $user = User::factory()->create([
        'password' => 'OldSecurePassword-123!',
        'email_verified_at' => now(),
    ]);
    $oldRememberToken = $user->remember_token;
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'OldSecurePassword-123!',
    ])->assertRedirect();
    expect(DB::table('sessions')->where('user_id', $user->getKey())->exists())->toBeTrue();
    $otherSessionId = str_repeat('b', 40);
    DB::table('sessions')->insert([
        'id' => $otherSessionId, 'user_id' => $user->getKey(), 'payload' => '', 'last_activity' => now()->timestamp,
    ]);

    $this->put('/user/password', [
        'current_password' => 'wrong-password',
        'password' => 'NewSecurePassword-456!',
        'password_confirmation' => 'NewSecurePassword-456!',
    ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

    expect(Hash::check('OldSecurePassword-123!', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->remember_token)->toBe($oldRememberToken);

    $this->put('/user/password', [
        'current_password' => 'OldSecurePassword-123!',
        'password' => 'NewSecurePassword-456!',
        'password_confirmation' => 'NewSecurePassword-456!',
    ])->assertRedirect();

    expect(Hash::check('NewSecurePassword-456!', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe($oldRememberToken)
        ->and(DB::table('sessions')->where('id', $otherSessionId)->exists())->toBeFalse();
    $this->assertDatabaseHas('audit_logs', ['user_id' => $user->getKey(), 'action' => 'account.password_changed']);
});
