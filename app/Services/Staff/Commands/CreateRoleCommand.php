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

class CreateRoleCommand
{
    /** @param list<string> $permissions */
    public function execute(User $actor, Business $business, string $name, array $permissions): Role
    {
        abort_unless($actor->hasBusinessPermission((int) $business->getKey(), 'roles.create'), 403);
        $this->assertDelegable($actor, (int) $business->getKey(), $permissions);
        $slug = Str::slug($name);

        if (in_array($slug, array_column(BusinessRole::cases(), 'value'), true)) {
            throw ValidationException::withMessages(['name' => 'Built-in role names are reserved.']);
        }

        if ($slug === '' || Role::query()->where('business_id', $business->getKey())->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Choose a unique role name.']);
        }

        return DB::transaction(function () use ($actor, $business, $name, $permissions, $slug): Role {
            $role = Role::query()->create([
                'business_id' => $business->getKey(),
                'name' => trim($name),
                'slug' => $slug,
                'permissions' => array_values(array_unique($permissions)),
            ]);
            app(RecordAuditLogCommand::class)->execute(
                (int) $business->getKey(), (int) $actor->getKey(), 'roles.created', Role::class,
                (int) $role->getKey(), null, ['name' => $role->name, 'permissions' => $role->permissions],
            );

            return $role;
        });
    }

    /** @param list<string> $permissions */
    private function assertDelegable(User $actor, int $businessId, array $permissions): void
    {
        $allowed = BusinessRole::permissions();
        foreach ($permissions as $permission) {
            abort_unless(isset($allowed[$permission]), 422, 'Unknown permission.');
            abort_unless($actor->hasBusinessPermission($businessId, $permission), 403, 'You cannot delegate permissions you do not hold.');
        }
    }
}
