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

class UpdateAccountPasswordCommand
{
    public function __construct(
        private readonly AccountSessionRepository $sessions,
        private readonly RecordAuditLogCommand $audit,
    ) {}

    public function execute(int $userId, string $password, string $currentSessionId): void
    {
        DB::transaction(function () use ($userId, $password, $currentSessionId): void {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
                'password_change_required' => false,
                'temporary_password_expires_at' => null,
            ])->save();

            $this->sessions->revokeOtherSessions($userId, $currentSessionId);
            $this->audit->execute(
                $this->businessId($userId),
                $userId,
                'account.password_changed',
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
