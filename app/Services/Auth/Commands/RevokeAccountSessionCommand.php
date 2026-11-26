<?php

namespace App\Services\Auth\Commands;

use App\Repositories\Auth\AccountSessionRepository;

class RevokeAccountSessionCommand
{
    public function __construct(private readonly AccountSessionRepository $sessions) {}

    public function execute(int $userId, string $sessionId, string $currentSessionId): bool
    {
        return $this->sessions->revokeSession($userId, $sessionId, $currentSessionId);
    }
}
