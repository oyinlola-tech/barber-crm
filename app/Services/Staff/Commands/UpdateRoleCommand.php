<?php

namespace App\Services\Staff\Commands;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateRoleCommand
{
    /** @param list<string> $permissions */
    public function execute(User $actor, Business $business, int $roleId, string $name, array $permissions): Role
    {
        $businessId = (int) $business->getKey();
        abort_unless($actor->hasBusinessPermission($businessId, 'roles.update'), 403);
        $this->assertDelegable($actor, $businessId, $permissions);

        return DB::transaction(function () use ($actor, $businessId, $roleId, $name, $permissions): Role {
            $role = Role::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($roleId);
            $oldSlug = $role->slug;
            $oldName = $role->name;
            $oldPermissions = $role->permissions;
            $newSlug = Str::slug($name);
            if ($newSlug === BusinessRole::Owner->value) {
                throw ValidationException::withMessages(['name' => 'The ownership role is reserved.']);
            }
            if ($newSlug === '' || Role::query()->where('business_id', $businessId)->where('slug', $newSlug)->whereKeyNot($roleId)->exists()) {
                throw ValidationException::withMessages(['name' => 'Choose a unique role name.']);
            }
            $role->forceFill(['name' => trim($name), 'slug' => $newSlug, 'permissions' => array_values(array_unique($permissions))])->save();
            DB::table('business_members')->where('business_id', $businessId)->where('role', $oldSlug)->update(['role' => $newSlug]);
            DB::table('staff_invitations')->where('business_id', $businessId)->where('role_slug', $oldSlug)
                ->whereNull('accepted_at')->whereNull('revoked_at')->update(['role_slug' => $newSlug]);
            app(RecordAuditLogCommand::class)->execute(
                $businessId, (int) $actor->getKey(), 'roles.updated', Role::class, $roleId,
                ['name' => $oldName, 'permissions' => $oldPermissions], ['name' => $role->name, 'permissions' => $role->permissions],
            );

            return $role->refresh();
        });
    }

    /** @param list<string> $permissions */
    private function assertDelegable(User $actor, int $businessId, array $permissions): void
    {
        foreach ($permissions as $permission) {
            abort_unless(isset(BusinessRole::permissions()[$permission]), 422, 'Unknown permission.');
            abort_unless($actor->hasBusinessPermission($businessId, $permission), 403, 'You cannot delegate permissions you do not hold.');
        }
    }
}
