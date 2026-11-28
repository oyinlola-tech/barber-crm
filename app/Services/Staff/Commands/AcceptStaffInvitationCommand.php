<?php

namespace App\Services\Staff\Commands;

use App\Models\BusinessMember;
use App\Models\Role;
use App\Models\StaffInvitation;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptStaffInvitationCommand
{
    public function execute(string $token): User
    {
        return DB::transaction(function () use ($token): User {
            $invitation = StaffInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || $invitation->accepted_at !== null || $invitation->revoked_at !== null || $invitation->expires_at->isPast()) {
                throw ValidationException::withMessages(['token' => 'This invitation is invalid, expired, or already used.']);
            }

            if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])->exists()) {
                throw ValidationException::withMessages(['token' => 'This invitation can no longer be used. Contact the barbershop owner.']);
            }

            $roleExists = Role::query()
                ->where('slug', $invitation->role_slug)
                ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $invitation->business_id))
                ->orderByRaw('business_id is null asc')
                ->exists();

            if (! $roleExists || $invitation->role_slug === 'owner') {
                throw ValidationException::withMessages(['token' => 'The invited role is no longer available.']);
            }

            if ($invitation->temporary_password_hash === null) {
                throw ValidationException::withMessages(['token' => 'This invitation has no valid temporary password. Ask the owner to send a new invitation.']);
            }

            $user = User::query()->forceCreate([
                'name' => $invitation->name,
                'email' => $invitation->email,
                'password' => $invitation->temporary_password_hash,
                'password_change_required' => true,
                'temporary_password_expires_at' => $invitation->expires_at,
                'email_verified_at' => now(),
            ]);

            BusinessMember::query()->create([
                'business_id' => $invitation->business_id,
                'user_id' => $user->getKey(),
                'role' => $invitation->role_slug,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            StaffProfile::query()->create([
                'business_id' => $invitation->business_id,
                'user_id' => $user->getKey(),
                'name' => $invitation->name,
                'email' => $invitation->email,
                'status' => 'active',
            ]);

            $invitation->forceFill(['accepted_at' => now(), 'temporary_password_hash' => null])->save();

            app(RecordAuditLogCommand::class)->execute(
                (int) $invitation->business_id, (int) $user->getKey(), 'staff.invitation.accepted',
                StaffInvitation::class, (int) $invitation->getKey(), null, ['role' => $invitation->role_slug],
            );

            return $user;
        });
    }
}
