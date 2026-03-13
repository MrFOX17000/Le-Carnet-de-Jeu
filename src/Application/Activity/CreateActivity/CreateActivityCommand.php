<?php

namespace App\Application\Activity\CreateActivity;

use App\Domain\Activity\ContextMode;

final class CreateActivityCommand
{
    public function __construct(
        private readonly int $groupId,
        private readonly string $name,
        private readonly int $creatorUserId,
        private readonly ContextMode $contextMode = ContextMode::RANKING,
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

    public function getContextMode(): ContextMode
    {
        return $this->contextMode;
    }
}
