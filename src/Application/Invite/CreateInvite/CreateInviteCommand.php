<?php

namespace App\Application\Invite\CreateInvite;

use App\Domain\Group\GroupRole;

final class CreateInviteCommand
{
    public function __construct(
        private readonly int $groupId,
        private readonly int $creatorUserId,
        private readonly string $email,
        private readonly GroupRole $role = GroupRole::MEMBER
    ) {
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    public function getCreatorUserId(): int
    {
        return $this->creatorUserId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): GroupRole
    {
        return $this->role;
    }
}