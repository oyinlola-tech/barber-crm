<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $grants = [
        'manager' => ['services.view', 'services.create'],
        'barber' => ['services.view'],
        'receptionist' => ['services.view'],
    ];

    public function up(): void
    {
        $this->changePermissions(add: true);
    }

    public function down(): void
    {
        $this->changePermissions(add: false);
    }

    private function changePermissions(bool $add): void
    {
        foreach ($this->grants as $slug => $permissions) {
            $role = DB::table('roles')->whereNull('business_id')->where('slug', $slug)->first(['id', 'permissions']);
            if ($role === null) {
                continue;
            }

            $current = is_array($role->permissions)
                ? $role->permissions
                : json_decode((string) $role->permissions, true);
            if (! is_array($current)) {
                continue;
            }

            $updated = $add
                ? array_values(array_unique([...$current, ...$permissions]))
                : array_values(array_diff($current, $permissions));

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($updated, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }
};
