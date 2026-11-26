<?php

namespace App\Services\Staff\Queries;

use App\Models\Role;
use App\Models\StaffInvitation;
use App\Models\StaffProfile;
use Illuminate\Support\Facades\DB;

class ListStaffQuery
{
    /** @return array{staff: list<array{id:int,name:string,email:?string,phone:?string,status:string,role:string}>,roles:list<array{slug:string,name:string}>,invitations:list<array{id:int,name:string,email:string,role:string,expires_at:string}>} */
    public function execute(int $businessId): array
    {
        $staff = StaffProfile::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get()
            ->map(function (StaffProfile $profile) use ($businessId): array {
                $role = DB::table('business_members')->where('business_id', $businessId)->where('user_id', $profile->user_id)->value('role');

                return [
                    'id' => (int) $profile->getKey(),
                    'name' => $profile->name,
                    'email' => $profile->email,
                    'phone' => $profile->phone,
                    'status' => (string) $profile->status,
                    'role' => (string) ($role ?? 'unlinked'),
                ];
            })->values()->all();

        $roles = Role::query()->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $businessId))
            ->where('slug', '!=', 'owner')->orderBy('name')->get(['name', 'slug'])
            ->map(fn (Role $role): array => ['slug' => $role->slug, 'name' => $role->name])->values()->all();

        $invitations = StaffInvitation::query()->where('business_id', $businessId)->whereNull('accepted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())->latest()->get(['id', 'name', 'email', 'role_slug', 'expires_at'])
            ->map(fn (StaffInvitation $invitation): array => [
                'id' => (int) $invitation->getKey(), 'name' => $invitation->name, 'email' => $invitation->email,
                'role' => $invitation->role_slug, 'expires_at' => $invitation->expires_at->toIso8601String(),
            ])->values()->all();

        return ['staff' => $staff, 'roles' => $roles, 'invitations' => $invitations];
    }
}
