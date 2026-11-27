<?php

namespace App\Services\Auth\Commands;

use App\Models\Business;
use App\Models\User;
use App\Repositories\Auth\AccountSessionRepository;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetAccountPasswordCommand
{
    public function __construct(
        private readonly AccountSessionRepository $sessions,
        private readonly RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $userId, string $password): void
    {
        DB::transaction(function () use ($userId, $password): void {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $this->sessions->revokeAllSessions($userId);
            $this->audit->execute(
                $this->businessId($userId),
                $userId,
                'account.password_reset',
                User::class,
                $userId,
            );
        });
    }

    private function businessId(int $userId): ?int
    {
        $businessId = Business::query()
            ->where('is_active', true)
            ->whereHas('members', static fn (Builder $members) => $members->whereKey($userId))
            ->value('id');

        return $businessId === null ? null : (int) $businessId;
    }
}
