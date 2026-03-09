<?php

namespace App\Application\Activity\CreateActivity;

final class CreateActivityResult
{
    public function __construct(
        private readonly int $activityId,
        private readonly int $groupId,
    ) {
    }

    public function getActivityId(): int
    {
        return $this->activityId;
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }
}
