<?php

namespace App\Application\Session\CreateSession;

final class CreateSessionResult
{
    public function __construct(
        private readonly int $sessionId,
        private readonly int $groupId,
    ) {
    }

    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }
}
