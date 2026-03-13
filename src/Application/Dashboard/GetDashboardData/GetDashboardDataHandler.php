<?php

declare(strict_types=1);

namespace App\Application\Dashboard\GetDashboardData;

use App\Application\Rating\EloRatingService;
use App\Domain\Activity\ContextMode;
use App\Entity\Activity;
use App\Entity\Session;
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
        private readonly EloRatingService $eloRatingService,
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
                'mostPlayedActivityId' => $mostPlayedActivity['activityId'] ?? null,
                'mostPlayedActivityGroupId' => $mostPlayedActivity['groupId'] ?? null,
                'mostPlayedActivitySessions' => $mostPlayedActivity['sessionsCount'] ?? 0,
                'mostActiveGroupName' => $mostActiveGroup['groupName'] ?? null,
                'mostActiveGroupSessions' => $mostActiveGroup['sessionsCount'] ?? 0,
            ];

            $dashboardStats['hallOfFame'] = $this->buildSeasonHallOfFame($groupIds);

            if (null !== $mostPlayedActivity) {
                $favoriteSessions = $this->sessionRepository->findByActivityWithEntries((int) $mostPlayedActivity['activityId']);
                $favoriteActivity = $favoriteSessions !== [] ? $favoriteSessions[0]->getActivity() : null;

                if ($favoriteActivity instanceof Activity) {
                    $dashboardStats['favoriteActivityRecords'] = [
                        'activityId' => $favoriteActivity->getId(),
                        'groupId' => $mostPlayedActivity['groupId'],
                        'activityName' => $favoriteActivity->getName(),
                        'allTime' => $this->buildRecords($favoriteActivity, $this->buildActivityLeaderboard($favoriteActivity, $favoriteSessions)),
                        'seasonKey' => $this->resolveLatestSeasonKey($favoriteSessions),
                    ];

                    if (null !== $dashboardStats['favoriteActivityRecords']['seasonKey']) {
                        $seasonSessions = array_values(array_filter(
                            $favoriteSessions,
                            static fn (Session $session): bool => $session->getPlayedAt()?->format('Y-m') === $dashboardStats['favoriteActivityRecords']['seasonKey'],
                        ));

                        $dashboardStats['favoriteActivityRecords']['seasonLabel'] = $this->formatSeasonLabel($dashboardStats['favoriteActivityRecords']['seasonKey']);
                        $dashboardStats['favoriteActivityRecords']['season'] = $this->buildRecords($favoriteActivity, $this->buildActivityLeaderboard($favoriteActivity, $seasonSessions));
                    }
                }
            }
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

    /**
     * @return array<string, mixed>
     */
    private function buildSeasonHallOfFame(array $groupIds): array
    {
        $seasonKey = (new \DateTimeImmutable('now'))->format('Y-m');
        $seasonLabel = $this->formatSeasonLabel($seasonKey);
        $activityBuckets = [];
        $sessions = $this->sessionRepository->findByGroupIdsWithEntries($groupIds);

        foreach ($sessions as $session) {
            if (!$session instanceof Session || $session->getPlayedAt()?->format('Y-m') !== $seasonKey) {
                continue;
            }

            $activity = $session->getActivity();
            $group = $session->getGroup();
            if (!$activity instanceof Activity || null === $activity->getId() || null === $group?->getId()) {
                continue;
            }

            $bucketKey = (int) $activity->getId();
            if (!isset($activityBuckets[$bucketKey])) {
                $activityBuckets[$bucketKey] = [
                    'activity' => $activity,
                    'groupId' => $group->getId(),
                    'sessions' => [],
                ];
            }

            $activityBuckets[$bucketKey]['sessions'][] = $session;
        }

        if ($activityBuckets === []) {
            return [];
        }

        usort($activityBuckets, static function (array $left, array $right): int {
            return [count($right['sessions']), $right['activity']->getName()]
                <=> [count($left['sessions']), $left['activity']->getName()];
        });

        $selected = $activityBuckets[0];
        $activity = $selected['activity'];
        $sessions = $selected['sessions'];
        $leaderboard = $this->buildActivityLeaderboard($activity, $sessions);

        return [
            'seasonKey' => $seasonKey,
            'seasonLabel' => $seasonLabel,
            'activityId' => $activity->getId(),
            'activityName' => $activity->getName(),
            'groupId' => $selected['groupId'],
            'isMatchMode' => $activity->getContextMode()->isMatchBased(),
            'topThree' => array_slice($leaderboard, 0, 3),
        ];
    }

    /**
     * @param Session[] $sessions
     * @return array<int, array<string, int|float|string>>
     */
    private function buildActivityLeaderboard(Activity $activity, array $sessions): array
    {
        $participants = [];
        $matchEvents = [];

        foreach ($sessions as $session) {
            foreach ($session->getEntries() as $entry) {
                if ($activity->getContextMode() === ContextMode::RANKING) {
                    foreach ($entry->getScores() as $score) {
                        $key = $this->buildParticipantKey($score->getUser()?->getId(), (string) $score->getParticipantName());
                        if (!isset($participants[$key])) {
                            $participants[$key] = [
                                'label' => $score->getUser()?->getDisplayName() ?: $score->getUser()?->getEmail() ?: (string) $score->getParticipantName(),
                                'pointsTotal' => 0.0,
                                'entriesCount' => 0,
                                'bestScore' => null,
                            ];
                        }

                        $numericScore = (float) $score->getScore();
                        $participants[$key]['pointsTotal'] += $numericScore;
                        ++$participants[$key]['entriesCount'];
                        $participants[$key]['bestScore'] = null === $participants[$key]['bestScore']
                            ? $numericScore
                            : max((float) $participants[$key]['bestScore'], $numericScore);
                    }

                    continue;
                }

                $match = $entry->getEntryMatch();
                if (null === $match) {
                    continue;
                }

                $homeKey = $this->buildParticipantKey($match->getHomeUser()?->getId(), (string) $match->getHomeName());
                $awayKey = $this->buildParticipantKey($match->getAwayUser()?->getId(), (string) $match->getAwayName());

                foreach ([
                    [$homeKey, $match->getHomeUser()?->getDisplayName() ?: $match->getHomeUser()?->getEmail() ?: (string) $match->getHomeName(), (int) $match->getHomeScore(), (int) $match->getAwayScore()],
                    [$awayKey, $match->getAwayUser()?->getDisplayName() ?: $match->getAwayUser()?->getEmail() ?: (string) $match->getAwayName(), (int) $match->getAwayScore(), (int) $match->getHomeScore()],
                ] as [$key, $label, $scored, $conceded]) {
                    if (!isset($participants[$key])) {
                        $participants[$key] = [
                            'label' => $label,
                            'wins' => 0,
                            'draws' => 0,
                            'losses' => 0,
                            'scored' => 0,
                            'conceded' => 0,
                        ];
                    }

                    $participants[$key]['scored'] += $scored;
                    $participants[$key]['conceded'] += $conceded;

                    if ($scored > $conceded) {
                        ++$participants[$key]['wins'];
                    } elseif ($scored < $conceded) {
                        ++$participants[$key]['losses'];
                    } else {
                        ++$participants[$key]['draws'];
                    }
                }

                $matchEvents[] = [
                    'playedAt' => $session->getPlayedAt(),
                    'homeKey' => $homeKey,
                    'awayKey' => $awayKey,
                    'homeScore' => (int) $match->getHomeScore(),
                    'awayScore' => (int) $match->getAwayScore(),
                ];
            }
        }

        if ($activity->getContextMode() === ContextMode::RANKING) {
            $rows = [];
            foreach ($participants as $participant) {
                $rows[] = [
                    'label' => (string) $participant['label'],
                    'pointsTotal' => (float) $participant['pointsTotal'],
                    'entriesCount' => (int) $participant['entriesCount'],
                    'bestScore' => (float) ($participant['bestScore'] ?? 0.0),
                ];
            }

            usort($rows, static fn (array $left, array $right): int => [$right['pointsTotal'], $left['label']] <=> [$left['pointsTotal'], $right['label']]);
            return $rows;
        }

        $elo = $this->eloRatingService->calculateFromEvents(
            $matchEvents,
            1000,
            24.0,
            $activity->getName(),
            $activity->getContextMode()->value,
        );
        $rows = [];
        foreach ($participants as $key => $participant) {
            $rows[] = [
                'label' => (string) $participant['label'],
                'mmr' => $elo[$key] ?? 1000,
                'wins' => (int) $participant['wins'],
                'draws' => (int) $participant['draws'],
                'losses' => (int) $participant['losses'],
                'scored' => (int) ($participant['scored'] ?? 0),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => [$right['mmr'], $right['wins'], $left['label']] <=> [$left['mmr'], $left['wins'], $right['label']]);

        return $rows;
    }

    private function buildParticipantKey(?int $userId, string $fallbackName): string
    {
        if (null !== $userId) {
            return 'user-' . $userId;
        }

        return 'name-' . mb_strtolower(trim($fallbackName));
    }

    private function formatSeasonLabel(string $season): string
    {
        [$year, $month] = explode('-', $season);
        return sprintf('S%d-%s', (int) $month, $year);
    }

    /**
     * @param array<int, array<string, int|float|string>> $leaderboard
     * @return array<int, array{title:string,value:string,meta:string}>
     */
    private function buildRecords(Activity $activity, array $leaderboard): array
    {
        if ([] === $leaderboard) {
            return [];
        }

        if ($activity->getContextMode() === ContextMode::RANKING) {
            $bestScore = $leaderboard;
            usort($bestScore, static fn (array $left, array $right): int => [$right['bestScore'], $left['label']] <=> [$left['bestScore'], $right['label']]);

            return [
                [
                    'title' => 'Leader cumulé',
                    'value' => (string) $leaderboard[0]['label'],
                    'meta' => sprintf('%s pts cumulés', number_format((float) $leaderboard[0]['pointsTotal'], 0, ',', ' ')),
                ],
                [
                    'title' => 'Record de score',
                    'value' => sprintf('%s pts', number_format((float) $bestScore[0]['bestScore'], 0, ',', ' ')),
                    'meta' => (string) $bestScore[0]['label'],
                ],
            ];
        }

        $wins = $leaderboard;
        usort($wins, static fn (array $left, array $right): int => [$right['wins'], $left['label']] <=> [$left['wins'], $right['label']]);
        $attack = $leaderboard;
        usort($attack, static fn (array $left, array $right): int => [$right['scored'], $left['label']] <=> [$left['scored'], $right['label']]);

        return [
            [
                'title' => 'Leader MMR',
                'value' => (string) $leaderboard[0]['label'],
                'meta' => sprintf('%d MMR', (int) round((float) $leaderboard[0]['mmr'])),
            ],
            [
                'title' => 'Plus de victoires',
                'value' => (string) $wins[0]['label'],
                'meta' => sprintf('%d victoire%s', (int) $wins[0]['wins'], (int) $wins[0]['wins'] > 1 ? 's' : ''),
            ],
            [
                'title' => 'Meilleure attaque',
                'value' => (string) $attack[0]['label'],
                'meta' => sprintf('%d points marqués', (int) $attack[0]['scored']),
            ],
        ];
    }

    /**
     * @param Session[] $sessions
     */
    private function resolveLatestSeasonKey(array $sessions): ?string
    {
        foreach ($sessions as $session) {
            if ($session->getPlayedAt() instanceof \DateTimeImmutable) {
                return $session->getPlayedAt()->format('Y-m');
            }
        }

        return null;
    }
}

