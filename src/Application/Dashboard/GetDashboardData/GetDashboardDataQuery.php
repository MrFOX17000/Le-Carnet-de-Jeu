<?php

declare(strict_types=1);

namespace App\Application\Dashboard\GetDashboardData;

final class GetDashboardDataQuery
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function getUserId(): int
    {
        return $this->userId;
    }
}
