<?php

namespace App\Application\Session\EnableSessionShare;

final class EnableSessionShareResult
{
    public function __construct(
        private readonly int $sessionId,
        private readonly string $unlistedToken,
    ) {
    }

    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    public function getUnlistedToken(): string
    {
        return $this->unlistedToken;
    }
}
