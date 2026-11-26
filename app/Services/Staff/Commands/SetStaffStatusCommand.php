<?php

namespace App\Services\Staff\Commands;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetStaffStatusCommand
{
    public function execute(User $actor, Business $business, int $staffProfileId, bool $activate): StaffProfile
    {
        $permission = $activate ? 'staff.activate' : 'staff.suspend';
        abort_unless($actor->hasBusinessPermission((int) $business->getKey(), $permission), 403);

        return DB::transaction(function () use ($actor, $business, $staffProfileId, $activate): StaffProfile {
            $profile = StaffProfile::query()->where('business_id', $business->getKey())->lockForUpdate()->findOrFail($staffProfileId);
            $membership = BusinessMember::query()
                ->where('business_id', $business->getKey())
                ->where('user_id', $profile->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->role === 'owner' || (int) $profile->user_id === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['staff' => 'The owner and your own account cannot be suspended or changed here.']);
            }

            $oldStatus = $membership->status;
            $newStatus = $activate ? 'active' : 'suspended';
            $membership->forceFill(['status' => $newStatus])->save();
            $profile->forceFill(['status' => $newStatus])->save();

            if (! $activate && $profile->user_id !== null) {
                DB::table('sessions')->where('user_id', $profile->user_id)->delete();
                User::query()->whereKey($profile->user_id)->update(['remember_token' => str()->random(60)]);
            }

            app(RecordAuditLogCommand::class)->execute(
                (int) $business->getKey(), (int) $actor->getKey(), $activate ? 'staff.activated' : 'staff.suspended',
                StaffProfile::class, (int) $profile->getKey(), ['status' => $oldStatus], ['status' => $newStatus],
            );

            return $profile->refresh()->load('user:id,name,email');
        });
    }
}
