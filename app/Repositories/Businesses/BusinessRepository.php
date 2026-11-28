<?php

namespace App\Repositories\Businesses;

use App\Dtos\Accounts\CreateBusinessOwnerDto;
use App\Interfaces\Repositories\BusinessRepositoryInterface;
use App\Models\Business;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessRepository implements BusinessRepositoryInterface
{
    public function createWithOwner(CreateBusinessOwnerDto $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data->ownerName,
                'email' => $data->ownerEmail,
                'password' => $data->password,
            ]);
            $user->forceFill([
                'password_change_required' => true,
                'temporary_password_expires_at' => now()->addHours(24),
            ])->save();

            $business = Business::create([
                'name' => $data->businessName,
                'slug' => Str::slug($data->businessName).'-'.Str::lower(Str::random(6)),
            ]);

            $business->members()->attach($user, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
            StaffProfile::query()->create([
                'business_id' => $business->getKey(),
                'user_id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'status' => 'active',
            ]);

            return $user;
        });
    }
}
