<?php

namespace App\Application\Invite\AcceptInvite;

final class AcceptInviteResult
{
    public function __construct(
        private readonly int $groupId,
        private readonly int $memberId,
    ) {
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    public function getMemberId(): int
    {
        return $this->memberId;
    }
}
