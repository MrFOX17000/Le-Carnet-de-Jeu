<?php

namespace App\Application\Activity\CreateActivity;

final class CreateActivityCommand
{
    public function __construct(
        private readonly int $groupId,
        private readonly string $name,
        private readonly int $creatorUserId,
    ) {
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatorUserId(): int
    {
        return $this->creatorUserId;
    }
}
