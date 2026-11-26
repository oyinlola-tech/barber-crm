<?php

namespace App\Services\Auth\Queries;

use App\Repositories\Auth\AccountSessionRepository;

class ListAccountSessionsQuery
{
    public function __construct(private readonly AccountSessionRepository $sessions) {}

    /** @return list<array{id: string, ip_address: ?string, user_agent: ?string, last_activity: int, is_current: bool}> */
    public function execute(int $userId, string $currentSessionId): array
    {
        return array_map(
            static fn (array $session): array => $session + ['is_current' => $session['id'] === $currentSessionId],
            $this->sessions->forUser($userId),
        );
    }
}
