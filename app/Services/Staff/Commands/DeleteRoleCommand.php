<?php

namespace App\Services\Staff\Commands;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRoleCommand
{
    public function execute(User $actor, Business $business, int $roleId): void
    {
        $businessId = (int) $business->getKey();
        abort_unless($actor->hasBusinessPermission($businessId, 'roles.delete'), 403);

        DB::transaction(function () use ($actor, $businessId, $roleId): void {
            $role = Role::query()->where('business_id', $businessId)->lockForUpdate()->findOrFail($roleId);
            if (DB::table('business_members')->where('business_id', $businessId)->where('role', $role->slug)->exists()) {
                throw ValidationException::withMessages(['role' => 'Reassign staff before deleting this role.']);
            }
            if (DB::table('staff_invitations')->where('business_id', $businessId)->where('role_slug', $role->slug)
                ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['role' => 'Revoke or let pending invitations expire before deleting this role.']);
            }
            app(RecordAuditLogCommand::class)->execute(
                $businessId, (int) $actor->getKey(), 'roles.deleted', Role::class, $roleId,
                ['name' => $role->name, 'permissions' => $role->permissions], null,
            );
            $role->delete();
        });
    }
}
