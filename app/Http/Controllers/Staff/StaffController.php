<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Services\Staff\Commands\AssignStaffRoleCommand;
use App\Services\Staff\Commands\InviteStaffMemberCommand;
use App\Services\Staff\Commands\RevokeStaffInvitationCommand;
use App\Services\Staff\Commands\SetStaffStatusCommand;
use App\Services\Staff\Commands\UpdateStaffProfileCommand;
use App\Services\Staff\Queries\ListStaffQuery;
use App\Validators\Staff\AssignStaffRoleRequest;
use App\Validators\Staff\InviteStaffRequest;
use App\Validators\Staff\RevokeStaffInvitationRequest;
use App\Validators\Staff\SetStaffStatusRequest;
use App\Validators\Staff\UpdateStaffProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request, ListStaffQuery $query): Response
    {
        $business = $request->attributes->get('currentBusiness');
        $this->authorize('viewAny', [StaffProfile::class, $business]);

        $data = $query->execute((int) $business->getKey());
        $data['roles'] = array_values(array_filter($data['roles'], function (array $role) use ($request, $business): bool {
            $permissions = Role::query()->where('slug', $role['slug'])
                ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $business->getKey()))
                ->value('permissions') ?? [];

            return $role['slug'] !== 'owner' && collect($permissions)->every(
                fn (string $permission): bool => $request->user()->hasBusinessPermission((int) $business->getKey(), $permission),
            );
        }));
        $data['capabilities'] = [
            'invite' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'staff.invite'),
            'assign' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'roles.assign'),
            'suspend' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'staff.suspend'),
            'activate' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'staff.activate'),
            'update' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'staff.update'),
            'revokeInvitation' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'staff.invite'),
            'viewRoles' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'roles.view'),
        ];
        if (! $data['capabilities']['invite'] && ! $data['capabilities']['assign']) {
            $data['roles'] = [];
        }
        if (! $data['capabilities']['invite']) {
            $data['invitations'] = [];
        }

        return Inertia::render('staff/index', $data);
    }

    public function invite(InviteStaffRequest $request, InviteStaffMemberCommand $command): RedirectResponse
    {
        $this->authorize('invite', [StaffProfile::class, $request->attributes->get('currentBusiness')]);
        $command->execute($request->user(), $request->attributes->get('currentBusiness'), $request->validated());

        return to_route('staff.index')->with('success', 'A secure invitation has been sent.');
    }

    public function setStatus(SetStaffStatusRequest $request, int $staff, SetStaffStatusCommand $command): RedirectResponse
    {
        $profile = StaffProfile::query()->where('business_id', $request->attributes->get('currentBusiness')->getKey())->findOrFail($staff);
        $this->authorize($request->validated('status') === 'active' ? 'activate' : 'suspend', $profile);
        $command->execute(
            $request->user(), $request->attributes->get('currentBusiness'), $staff,
            $request->validated('status') === 'active',
        );

        return to_route('staff.index')->with('success', 'Staff account status updated.');
    }

    public function updateProfile(UpdateStaffProfileRequest $request, int $staff, UpdateStaffProfileCommand $command): RedirectResponse
    {
        $profile = StaffProfile::query()
            ->where('business_id', $request->attributes->get('currentBusiness')->getKey())
            ->findOrFail($staff);
        $this->authorize('update', $profile);
        $command->execute(
            $request->user(),
            $request->attributes->get('currentBusiness'),
            $staff,
            $request->validated('name'),
            $request->validated('phone'),
        );

        return to_route('staff.index')->with('success', 'Staff profile updated.');
    }

    public function revokeInvitation(RevokeStaffInvitationRequest $request, int $invitation, RevokeStaffInvitationCommand $command): RedirectResponse
    {
        $command->execute(
            $request->user(),
            $request->attributes->get('currentBusiness'),
            $invitation,
        );

        return to_route('staff.index')->with('success', 'Staff invitation revoked.');
    }

    public function assignRole(AssignStaffRoleRequest $request, int $staff, AssignStaffRoleCommand $command): RedirectResponse
    {
        $command->execute(
            $request->user(), $request->attributes->get('currentBusiness'), $staff,
            $request->validated('role_slug'),
        );

        return to_route('staff.index')->with('success', 'Staff role updated.');
    }
}
