<?php

namespace App\Application\Api\Group\GetGroupDetails;

final class GetGroupDetailsQuery
{
    public function __construct(
        public readonly int $groupId,
        public readonly int $userId,
    ) {
    }
}
