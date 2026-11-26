<?php

namespace App\Configs;

use InvalidArgumentException;

final class AuthorizationConfig
{
    /** @return list<string> */
    public function privilegedPermissions(): array
    {
        return $this->permissionList('authorization.privileged_permissions');
    }

    /** @return list<string> */
    public function privilegedPermissionGroups(): array
    {
        return $this->permissionList('authorization.privileged_permission_groups');
    }

    /** @return list<string> */
    private function permissionList(string $key): array
    {
        $permissions = config($key, []);
        if (! is_array($permissions)) {
            throw new InvalidArgumentException("The {$key} configuration must be a list of permissions.");
        }

        foreach ($permissions as $permission) {
            if (! is_string($permission) || preg_match('/^[a-z]+(?:\.[a-z*]+)+$/', $permission) !== 1) {
                throw new InvalidArgumentException("The {$key} configuration contains an invalid permission.");
            }
        }

        return array_values($permissions);
    }
}
