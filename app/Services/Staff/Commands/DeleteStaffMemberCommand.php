<?php

namespace App\Services\Staff\Commands;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeleteStaffMemberCommand
{
    public function __construct(private readonly RecordAuditLogCommand $audit) {}

    public function execute(User $actor, Business $business, int $staffProfileId): StaffProfile
    {
        $businessId = (int) $business->getKey();
        abort_unless($actor->hasBusinessPermission($businessId, 'staff.delete'), 403);

        return DB::transaction(function () use ($actor, $businessId, $staffProfileId): StaffProfile {
            $profile = StaffProfile::query()
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->findOrFail($staffProfileId);

            if ($profile->user_id === null || $profile->status === 'deleted') {
                throw ValidationException::withMessages(['staff' => 'This staff account has already been removed.']);
            }

            $membership = BusinessMember::query()
                ->where('business_id', $businessId)
                ->where('user_id', $profile->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->role === 'owner' || (int) $profile->user_id === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['staff' => 'The owner and your own account are protected.']);
            }

            $user = User::query()->lockForUpdate()->findOrFail($profile->user_id);
            $formerUserId = (int) $user->getKey();
            $formerEmail = (string) $user->email;
            $before = ['status' => $profile->status, 'role' => $membership->role];

            DB::table('sessions')->where('user_id', $formerUserId)->delete();
            DB::table('password_reset_tokens')->where('email', $formerEmail)->delete();

            $user->forceFill([
                'name' => 'Deleted staff member',
                'email' => "deleted-staff-{$businessId}-{$formerUserId}@redacted.invalid",
                'password' => Str::random(96),
                'email_verified_at' => null,
                'remember_token' => Str::random(60),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $membership->delete();
            $profile->forceFill([
                'name' => 'Deleted staff member',
                'email' => null,
                'phone' => null,
                'status' => 'deleted',
            ])->save();

            $this->audit->execute(
                $businessId,
                (int) $actor->getKey(),
                'staff.deleted',
                StaffProfile::class,
                (int) $profile->getKey(),
                $before,
                ['status' => 'deleted', 'account_access' => 'revoked', 'profile_data' => 'redacted'],
            );

            return $profile->refresh();
        });
    }
}
