<?php

namespace App\Services\Auth\Commands;

use App\Repositories\Auth\AccountSessionRepository;

class RevokeOtherAccountSessionsCommand
{
    public function __construct(private readonly AccountSessionRepository $sessions) {}

    public function execute(int $userId, string $currentSessionId): int
    {
        return $this->sessions->revokeOtherSessions($userId, $currentSessionId);
    }
}
