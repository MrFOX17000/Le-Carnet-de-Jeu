<?php

namespace App\Application\Invite\CreateInvite;

final class CreateInviteResult
{
    public function __construct(
        private readonly int $inviteId,
        private readonly string $token,
    ) {
    }

    public function getInviteId(): int
    {
        return $this->inviteId;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}