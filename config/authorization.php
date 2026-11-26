<?php

return [
    'privileged_permissions' => [
        'staff.invite',
        'staff.update',
        'staff.suspend',
        'staff.activate',
        'staff.delete',
        'roles.create',
        'roles.update',
        'roles.delete',
        'roles.assign',
        'settings.update',
        'whatsapp.configure',
        'whatsapp.disconnect',
    ],
    'privileged_permission_groups' => [
        'staff.*',
        'roles.*',
        'settings.*',
        'whatsapp.*',
    ],
];
