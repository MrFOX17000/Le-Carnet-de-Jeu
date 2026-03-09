<?php

namespace App\Application\Group\CreateGroup;

final class CreateGroupResult
{
    public function __construct(
        private readonly int $groupId
    ) {
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }
}