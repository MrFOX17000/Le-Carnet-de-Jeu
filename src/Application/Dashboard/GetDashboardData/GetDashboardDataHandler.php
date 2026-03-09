<?php

declare(strict_types=1);

namespace App\Application\Dashboard\GetDashboardData;

use App\Entity\GameGroup;
use App\Repository\GameGroupRepository;
use App\Repository\EntryRepository;
use App\Repository\EntryScoreRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\InviteRepository;
use App\Repository\SessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

final class GetDashboardDataHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameGroupRepository $groupRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly InviteRepository $inviteRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly EntryRepository $entryRepository,
        private readonly EntryScoreRepository $entryScoreRepository,
    ) {}

    public function handle(GetDashboardDataQuery $query): GetDashboardDataResult
    {
        $userId = $query->getUserId();

        // Fetch all groups where user is a member
        $groups = $this->groupRepository->findGroupsForUser($userId);

        // Build a map of group ID to user's role in that group
        $groupRoles = [];
        $groupIds = array_values(array_filter(
            array_map(static fn (GameGroup $g): ?int => $g->getId(), $groups),
            static fn (?int $id): bool => null !== $id,
        ));
        
        if (!empty($groupIds)) {
            // Query group members directly
            $members = $this->groupMemberRepository->findBy([
                'user' => $userId,
            ]);
            
            foreach ($members as $member) {
                $groupRoles[$member->getGroup()->getId()] = $member->getRole()->value;
            }
        }

        // Fetch 10 most recent sessions from user's groups
        $recentSessions = [];
        if (!empty($groupIds)) {
            $recentSessions = $this->sessionRepository->findRecentSessionsByGroupIds($groupIds, 10);
        }

        // Fetch pending (non-expired, non-accepted) invites for this user's email
        $user = $this->entityManager->getRepository('App\Entity\User')->find($userId);
        $pendingInvites = [];
        if ($user) {
            $pendingInvites = $this->inviteRepository->findPendingInvites($user->getEmail());
        }

        $memberStats = [];
        $activityStats = [];
        $dashboardStats = [];

        if (!empty($groupIds)) {
            $selectedGroup = $groups[0];
            $selectedGroupId = (int) $selectedGroup->getId();

            $winLoss = $this->entryRepository->countWinLossForUserInGroup($userId, $selectedGroupId);
            $memberStats = [
                'groupId' => $selectedGroupId,
                'groupName' => $selectedGroup->getName(),
                'sessionsCount' => $this->entryRepository->countDistinctSessionsCreatedByUserInGroup($userId, $selectedGroupId),
                'entriesCount' => $this->entryRepository->countEntriesCreatedByUserInGroup($userId, $selectedGroupId),
                'totalPoints' => $this->entryScoreRepository->sumScoreForEntriesCreatedByUserInGroup($userId, $selectedGroupId),
                'matchesPlayed' => $this->entryRepository->countMatchEntriesCreatedByUserInGroup($userId, $selectedGroupId),
                'wins' => $winLoss['wins'],
                'losses' => $winLoss['losses'],
            ];

            $mostPlayedActivity = $this->sessionRepository->findMostPlayedActivityByGroupIds($groupIds);
            if (null !== $mostPlayedActivity) {
                $activityStats = [
                    'activityName' => $mostPlayedActivity['activityName'],
                    'sessionsCount' => $mostPlayedActivity['sessionsCount'],
                    'lastPlayedAt' => $mostPlayedActivity['lastPlayedAt'],
                    'topParticipants' => $this->entryScoreRepository->findTopParticipantsForActivityInGroups(
                        $mostPlayedActivity['activityId'],
                        $groupIds,
                        3,
                    ),
                ];
            }

            $weekStart = (new \DateTimeImmutable('now'))->setTime(0, 0)->modify('monday this week');
            $mostActiveGroup = $this->sessionRepository->findMostActiveGroupByGroupIds($groupIds);

            $dashboardStats = [
                'sessionsThisWeek' => $this->sessionRepository->countSessionsThisWeekByGroupIds($groupIds, $weekStart),
                'mostPlayedActivityName' => $mostPlayedActivity['activityName'] ?? null,
                'mostPlayedActivitySessions' => $mostPlayedActivity['sessionsCount'] ?? 0,
                'mostActiveGroupName' => $mostActiveGroup['groupName'] ?? null,
                'mostActiveGroupSessions' => $mostActiveGroup['sessionsCount'] ?? 0,
            ];
        }

        return new GetDashboardDataResult(
            new ArrayCollection($groups),
            new ArrayCollection($recentSessions),
            new ArrayCollection($pendingInvites),
            $groupRoles,
            $memberStats,
            $activityStats,
            $dashboardStats,
        );
    }
}

