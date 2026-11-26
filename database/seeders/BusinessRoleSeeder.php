<?php

namespace Database\Seeders;

use App\Enums\BusinessRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

class BusinessRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            BusinessRole::Owner->value => ['*'],
            BusinessRole::Manager->value => ['dashboard.view', 'customers.*', 'consent.*', 'visits.*', 'staff.view', 'staff.invite', 'staff.update', 'staff.suspend', 'staff.activate', 'roles.view', 'roles.assign', 'permissions.view', 'conversations.*', 'messages.*', 'automations.*', 'templates.*', 'reviews.*', 'analytics.view', 'whatsapp.view', 'settings.view', 'audit.view'],
            BusinessRole::Barber->value => ['customers.view', 'customers.note', 'visits.view', 'visits.create'],
            BusinessRole::Receptionist->value => ['dashboard.view', 'customers.view', 'customers.create', 'customers.update', 'consent.view', 'consent.record', 'consent.revoke', 'visits.view', 'conversations.view', 'conversations.reply', 'reviews.view', 'reviews.create'],
            BusinessRole::Viewer->value => ['dashboard.view', 'customers.view', 'visits.view', 'analytics.view', 'reviews.view'],
        ];

        foreach ($roles as $slug => $permissions) {
            Role::query()->updateOrCreate(
                ['business_id' => null, 'slug' => $slug],
                ['name' => str($slug)->headline()->toString(), 'permissions' => $permissions],
            );
        }
    }
}
