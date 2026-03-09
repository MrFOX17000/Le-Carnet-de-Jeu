<?php

namespace App\Application\Api\Group\GetMyGroups;

final class GetMyGroupsQuery
{
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
