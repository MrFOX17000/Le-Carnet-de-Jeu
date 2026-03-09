<?php

namespace App\Application\Api\Session\GetSessionDetails;

final class GetSessionDetailsQuery
{
    public function __construct(
        public readonly int $sessionId,
        public readonly int $groupId,
        public readonly int $userId,
    ) {
    }
}
