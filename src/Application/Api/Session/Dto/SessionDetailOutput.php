<?php

namespace App\Application\Api\Session\Dto;

final class SessionDetailOutput
{
    /**
     * @param list<EntryOutput> $entries
     */
    public function __construct(
        public readonly int $id,
        public readonly int $groupId,
        public readonly int $activityId,
        public readonly string $activityName,
        public readonly ?string $title,
        public readonly string $playedAt,
        public readonly string $createdAt,
        public readonly int $createdById,
        public readonly string $createdByEmail,
        public readonly array $entries,
    ) {
    }
}
