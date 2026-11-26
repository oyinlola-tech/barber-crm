<?php

namespace App\Services\Staff\Commands;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateStaffProfileCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    public function execute(User $actor, Business $business, int $profileId, string $name, ?string $phone): StaffProfile
    {
        $businessId = (int) $business->getKey();
        abort_unless($actor->hasBusinessPermission($businessId, 'staff.update'), 403);

        return DB::transaction(function () use ($actor, $businessId, $profileId, $name, $phone): StaffProfile {
            $profile = StaffProfile::query()
                ->where('business_id', $businessId)
                ->lockForUpdate()
                ->findOrFail($profileId);

            if ($profile->user_id === null) {
                throw ValidationException::withMessages(['staff' => 'This profile is not linked to an active staff account.']);
            }

            $membership = BusinessMember::query()
                ->where('business_id', $businessId)
                ->where('user_id', $profile->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->role === 'owner') {
                throw ValidationException::withMessages(['staff' => 'The owner profile is protected.']);
            }

            $before = ['name' => $profile->name, 'phone' => $profile->phone];
            $profile->forceFill(['name' => trim($name), 'phone' => $phone === null ? null : trim($phone)])->save();
            User::query()->whereKey($profile->user_id)->update(['name' => trim($name)]);

            $this->audit->execute(
                $businessId,
                (int) $actor->getKey(),
                'staff.profile_updated',
                StaffProfile::class,
                (int) $profile->getKey(),
                $before,
                ['name' => $profile->name, 'phone' => $profile->phone],
            );

            return $profile->refresh()->load('user:id,name,email');
        });
    }
}
