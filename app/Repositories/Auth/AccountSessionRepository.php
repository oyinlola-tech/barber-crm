<?php

namespace App\Repositories\Auth;

use Illuminate\Support\Facades\DB;
use stdClass;

class AccountSessionRepository
{
    /** @return list<array{id: string, ip_address: ?string, user_agent: ?string, last_activity: int}> */
    public function forUser(int $userId): array
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(static fn (stdClass $session): array => [
                'id' => (string) $session->id,
                'ip_address' => $session->ip_address === null ? null : (string) $session->ip_address,
                'user_agent' => $session->user_agent === null ? null : mb_substr((string) $session->user_agent, 0, 240),
                'last_activity' => (int) $session->last_activity,
            ])
            ->all();
    }

    public function revokeOtherSessions(int $userId, string $currentSessionId): int
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '<>', $currentSessionId)
            ->delete();
    }

    public function revokeSession(int $userId, string $sessionId, string $currentSessionId): bool
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', $sessionId)
            ->where('id', '<>', $currentSessionId)
            ->delete() > 0;
    }
}
