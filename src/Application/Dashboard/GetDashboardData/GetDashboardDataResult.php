<?php

declare(strict_types=1);

namespace App\Application\Dashboard\GetDashboardData;

use App\Entity\GameGroup;
use App\Entity\Invite;
use App\Entity\Session;
use Doctrine\Common\Collections\Collection;

final class GetDashboardDataResult
{
    /**
     * @param Collection<int, GameGroup> $groups
     * @param Collection<int, Session> $recentSessions
     * @param Collection<int, Invite> $pendingInvites
     * @param array<int, string> $groupRoles Map of group ID to role
     * @param array<string, mixed> $memberStats
     * @param array<string, mixed> $activityStats
     * @param array<string, mixed> $dashboardStats
     */
    public function __construct(
        private readonly Collection $groups,
        private readonly Collection $recentSessions,
        private readonly Collection $pendingInvites,
        private readonly array $groupRoles,
        private readonly array $memberStats = [],
        private readonly array $activityStats = [],
        private readonly array $dashboardStats = [],
    ) {}

    /**
     * @return Collection<int, GameGroup>
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    /**
     * @return Collection<int, Session>
     */
    public function getRecentSessions(): Collection
    {
        return $this->recentSessions;
    }

    /**
     * @return Collection<int, Invite>
     */
    public function getPendingInvites(): Collection
    {
        return $this->pendingInvites;
    }

    /**
     * Get the role for the current user in a given group
     */
    public function getRoleForGroup(int $groupId): ?string
    {
        return $this->groupRoles[$groupId] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMemberStats(): array
    {
        return $this->memberStats;
    }

    /**
     * @return array<string, mixed>
     */
    public function getActivityStats(): array
    {
        return $this->activityStats;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboardStats(): array
    {
        return $this->dashboardStats;
    }
}

