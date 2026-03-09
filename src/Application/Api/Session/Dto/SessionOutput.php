<?php

namespace App\Application\Api\Session\Dto;

final class SessionOutput
{
    public function __construct(
        public readonly int $id,
        public readonly int $groupId,
        public readonly int $activityId,
        public readonly string $activityName,
        public readonly ?string $title,
        public readonly string $playedAt,
        public readonly int $entriesCount,
    ) {
    }
}
