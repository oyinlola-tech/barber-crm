<?php

namespace App\Services\Staff\Commands;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignStaffRoleCommand
{
    public function execute(User $actor, Business $business, int $staffProfileId, string $roleSlug): void
    {
        $businessId = (int) $business->getKey();
        abort_unless($actor->hasBusinessPermission($businessId, 'roles.assign'), 403);
        abort_if($roleSlug === 'owner', 403, 'Ownership cannot be assigned.');

        $role = Role::query()->where('slug', $roleSlug)
            ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $businessId))
            ->orderByRaw('business_id is null asc')
            ->firstOrFail();

        $this->assertDelegable($actor, $businessId, $role->permissions ?? []);

        DB::transaction(function () use ($actor, $businessId, $staffProfileId, $roleSlug): void {
            $profile = StaffProfile::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($staffProfileId);
            $membership = BusinessMember::query()->where('business_id', $businessId)
                ->where('user_id', $profile->user_id)->lockForUpdate()->firstOrFail();

            if ($membership->role === 'owner' || (int) $profile->user_id === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['role' => 'You cannot change the owner role or your own role.']);
            }

            $before = $membership->role;
            $membership->forceFill(['role' => $roleSlug])->save();
            app(RecordAuditLogCommand::class)->execute(
                $businessId, (int) $actor->getKey(), 'staff.role_assigned', BusinessMember::class,
                (int) $membership->getKey(), ['role' => $before], ['role' => $roleSlug],
            );
        });
    }

    /** @param list<string> $permissions */
    private function assertDelegable(User $actor, int $businessId, array $permissions): void
    {
        foreach ($permissions as $permission) {
            abort_unless($actor->hasBusinessPermission($businessId, $permission), 403, 'You cannot delegate permissions you do not hold.');
        }
    }
}
