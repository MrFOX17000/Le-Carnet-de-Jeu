<?php

namespace App\Application\Session\CreateSession;

final class CreateSessionCommand
{
    public function __construct(
        private readonly int $groupId,
        private readonly int $activityId,
        private readonly int $creatorUserId,
        private readonly ?\DateTimeImmutable $playedAt,
        private readonly ?string $title = null,
    ) {
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    public function getActivityId(): int
    {
        return $this->activityId;
    }

    public function getCreatorUserId(): int
    {
        return $this->creatorUserId;
    }

    public function getPlayedAt(): ?\DateTimeImmutable
    {
        return $this->playedAt;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }
}
