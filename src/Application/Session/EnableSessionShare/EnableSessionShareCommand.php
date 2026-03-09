<?php

namespace App\Application\Session\EnableSessionShare;

final class EnableSessionShareCommand
{
    public function __construct(
        private readonly int $sessionId,
        private readonly int $groupId,
        private readonly int $userIdRequestingShare,
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

    public function getUserIdRequestingShare(): int
    {
        return $this->userIdRequestingShare;
    }
}
