<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BusinessRole;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\Staff\Commands\CreateRoleCommand;
use App\Services\Staff\Commands\DeleteRoleCommand;
use App\Services\Staff\Commands\UpdateRoleCommand;
use App\Validators\Staff\StoreRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $business = $request->attributes->get('currentBusiness');
        $this->authorize('viewAny', [Role::class, $business]);
        $businessId = (int) $business->getKey();
        $permissionCatalogue = BusinessRole::permissions();
        $delegablePermissions = array_filter(
            $permissionCatalogue,
            fn (string $label, string $permission): bool => $request->user()->hasBusinessPermission($businessId, $permission),
            ARRAY_FILTER_USE_BOTH,
        );
        $roles = Role::query()->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $business->getKey()))
            ->orderBy('business_id')->orderBy('name')->get(['id', 'business_id', 'name', 'slug', 'permissions']);

        return Inertia::render('roles/index', [
            'roles' => $roles->map(fn (Role $role): array => [
                'id' => (int) $role->getKey(), 'name' => $role->name, 'slug' => $role->slug,
                'permissions' => $role->permissions ?? [], 'protected' => $role->business_id === null || $role->slug === 'owner',
                'canUpdate' => $request->user()->can('update', $role),
                'canDelete' => $request->user()->can('delete', $role),
            ])->values(),
            'permissions' => BusinessRole::permissions(),
            'delegablePermissions' => $delegablePermissions,
            'capabilities' => [
                'create' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'roles.create'),
                'update' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'roles.update'),
                'delete' => $request->user()->hasBusinessPermission((int) $business->getKey(), 'roles.delete'),
            ],
        ]);
    }

    public function store(StoreRoleRequest $request, CreateRoleCommand $command): RedirectResponse
    {
        $business = $request->attributes->get('currentBusiness');
        $this->authorize('create', [Role::class, $business]);
        $data = $request->validated();
        $command->execute($request->user(), $business, $data['name'], $data['permissions']);

        return to_route('roles.index')->with('success', 'Role created.');
    }

    public function update(StoreRoleRequest $request, int $role, UpdateRoleCommand $command): RedirectResponse
    {
        $business = $request->attributes->get('currentBusiness');
        $model = Role::query()->where('business_id', $business->getKey())->findOrFail($role);
        $this->authorize('update', $model);
        $data = $request->validated();
        $command->execute($request->user(), $business, $role, $data['name'], $data['permissions']);

        return to_route('roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Request $request, int $role, DeleteRoleCommand $command): RedirectResponse
    {
        $business = $request->attributes->get('currentBusiness');
        $model = Role::query()->where('business_id', $business->getKey())->findOrFail($role);
        $this->authorize('delete', $model);
        $command->execute($request->user(), $business, $role);

        return to_route('roles.index')->with('success', 'Role deleted.');
    }
}
