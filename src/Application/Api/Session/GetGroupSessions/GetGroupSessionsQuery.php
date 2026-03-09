<?php

namespace App\Application\Api\Session\GetGroupSessions;

final class GetGroupSessionsQuery
{
    public function __construct(
        public readonly int $groupId,
        public readonly int $userId,
    ) {
    }
}
