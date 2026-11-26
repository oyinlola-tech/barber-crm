<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'services.categories.create',
        'services.categories.update',
        'services.categories.delete',
    ];

    public function up(): void
    {
        $this->changeManagerPermissions(add: true);
    }

    public function down(): void
    {
        $this->changeManagerPermissions(add: false);
    }

    private function changeManagerPermissions(bool $add): void
    {
        $role = DB::table('roles')->whereNull('business_id')->where('slug', 'manager')->first(['id', 'permissions']);
        if ($role === null) {
            return;
        }

        $permissions = is_array($role->permissions)
            ? $role->permissions
            : json_decode((string) $role->permissions, true);
        if (! is_array($permissions)) {
            return;
        }

        $permissions = $add
            ? array_values(array_unique([...$permissions, ...self::PERMISSIONS]))
            : array_values(array_diff($permissions, self::PERMISSIONS));

        DB::table('roles')->where('id', $role->id)->update([
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }
};
