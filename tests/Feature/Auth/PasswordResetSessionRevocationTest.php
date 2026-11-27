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
    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'email' => $user->email,
        'token' => $token,
        'password' => 'NewSecurePassword-456!',
        'password_confirmation' => 'NewSecurePassword-456!',
    ])->assertRedirect();

    expect(Hash::check('NewSecurePassword-456!', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->getKey())->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'unrelated-user-session')->exists())->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});
