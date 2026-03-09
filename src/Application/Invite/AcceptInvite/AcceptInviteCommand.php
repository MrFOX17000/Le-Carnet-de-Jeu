<?php

namespace App\Application\Invite\AcceptInvite;

final class AcceptInviteCommand
{
    public function __construct(
        private readonly string $token,
        private readonly int $userId,
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}
