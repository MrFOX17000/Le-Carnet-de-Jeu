<?php

namespace App\Application\Group\CreateGroup;

final class CreateGroupCommand
{
    public function __construct(
        private readonly string $name,
        private readonly int $creatorUserId,
    ) {
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