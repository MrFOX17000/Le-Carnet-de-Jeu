<?php

namespace App\UI\Http\Controller\Member;

use App\Application\Rating\EloRatingService;
use App\Entity\Activity;
use App\Entity\EntryMatch;
use App\Entity\Session;
use App\Entity\User;
use App\Repository\GameGroupRepository;
use App\Repository\UserRepository;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class MemberProfileController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly GameGroupRepository $groupRepository,
        private readonly EloRatingService $eloRatingService,
    ) {
    }

    #[Route('/groups/{groupId}/members/{userId}', name: 'member_profile', methods: ['GET'])]
    public function __invoke(Request $request, int $groupId, int $userId): Response
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Charger le groupe
        $group = $this->groupRepository->find($groupId);
        if (null === $group) {
            throw new NotFoundHttpException('Group not found.');
        }

        // Vérifier les droits d'accès au groupe
        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);

        // Charger le membre
        $member = $this->userRepository->find($userId);
        if (null === $member) {
            throw new NotFoundHttpException('Member not found.');
        }

        // Vérifier que le membre appartient au groupe
        $isMemberOfGroup = false;
        $memberRole = null;
        $joinedAt = null;
        foreach ($group->getGroupMembers() as $membership) {
            if ($membership->getUser()?->getId() === $member->getId()) {
                $isMemberOfGroup = true;
                $memberRole = $membership->getRole()->value;
                $joinedAt = $membership->getJoinedAt();
                break;
            }
        }

        if (!$isMemberOfGroup) {
            throw new NotFoundHttpException('Member is not part of this group.');
        }

        $allSessions = $group->getSessions()->toArray();
        usort($allSessions, static fn (Session $left, Session $right): int => $right->getPlayedAt() <=> $left->getPlayedAt());

        $seasonOptions = $this->buildSeasonOptionsFromSessions($allSessions);
        $seasonFilter = trim($request->query->getString('season', 'all'));
        $selectedSeason = $this->resolveSeasonFilter($seasonFilter, $seasonOptions);
        $sessions = $this->filterSessionsBySeason($allSessions, $selectedSeason);

        $memberStats = [
            'total_sessions' => 0,
            'total_score_entries' => 0,
            'total_match_entries' => 0,
            'total_scores' => 0.0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'average_score' => 0.0,
            'best_score' => null,
        ];
        $recentForm = [];
        $activityBreakdown = [];
        $sessionSummaries = [];
        $activityMatchEvents = [];
        $activityRankingPools = [];

        foreach ($sessions as $session) {
            $sessionSummary = $this->buildSessionSummary($session, $member, $memberStats, $activityBreakdown, $recentForm, $activityMatchEvents, $activityRankingPools);

            if (null !== $sessionSummary) {
                $sessionSummaries[] = $sessionSummary;
                ++$memberStats['total_sessions'];
            }
        }

        $memberKey = $this->buildParticipantKey($member, (string) $member->getEmail());

        foreach ($activityBreakdown as &$activityStats) {
            $activityStats['sessions_count'] = count($activityStats['sessions']);
            $activityStats['average_score'] = $activityStats['score_entries_count'] > 0
                ? $activityStats['total_score'] / $activityStats['score_entries_count']
                : 0.0;
            $activityStats['win_rate'] = $activityStats['match_entries_count'] > 0
                ? ($activityStats['wins'] * 100) / $activityStats['match_entries_count']
                : 0.0;

            $activityStats['mmr'] = null;
            $activityStats['mmr_delta'] = null;

            if ($activityStats['context_mode'] !== 'ranking' && isset($activityMatchEvents[$activityStats['activity_id']])) {
                $eloRatings = $this->eloRatingService->calculateFromEvents(
                    $activityMatchEvents[$activityStats['activity_id']],
                    1000,
                    24.0,
                    (string) $activityStats['activity_name'],
                    (string) $activityStats['context_mode'],
                );

                if (isset($eloRatings[$memberKey])) {
                    $activityStats['mmr'] = $eloRatings[$memberKey];
                    $activityStats['mmr_delta'] = $eloRatings[$memberKey] - 1000;
                }
            }

            $activityStats['season_rank'] = null;
            $activityStats['season_honor_label'] = null;
        }
        unset($activityStats);

        $activityBreakdown = array_values($activityBreakdown);

        $seasonHonors = $this->buildSeasonHonors($activityRankingPools, $memberKey);
        $allTimeRecordClaims = $this->buildMemberRecordClaims($this->buildRankingPoolsFromSessions($allSessions), $memberKey);
        $seasonRecordClaims = $selectedSeason !== 'all'
            ? $this->buildMemberRecordClaims($activityRankingPools, $memberKey)
            : [];
        $seasonHonorMap = [];
        foreach ($seasonHonors as $honor) {
            $seasonHonorMap[$honor['activity_id']] = $honor;
        }

        foreach ($activityBreakdown as &$activityStats) {
            if (isset($seasonHonorMap[$activityStats['activity_id']])) {
                $activityStats['season_rank'] = $seasonHonorMap[$activityStats['activity_id']]['rank'];
                $activityStats['season_honor_label'] = $seasonHonorMap[$activityStats['activity_id']]['label'];
            }
        }
        unset($activityStats);

        usort($activityBreakdown, static function (array $left, array $right): int {
            return [
                $right['sessions_count'],
                $right['total_score'],
                $right['wins'],
                $left['activity_name'],
            ] <=> [
                $left['sessions_count'],
                $left['total_score'],
                $left['wins'],
                $right['activity_name'],
            ];
        });

        $memberStats['average_score'] = $memberStats['total_score_entries'] > 0
            ? $memberStats['total_scores'] / $memberStats['total_score_entries']
            : 0.0;
        $memberStats['win_rate'] = $memberStats['total_match_entries'] > 0
            ? ($memberStats['wins'] * 100) / $memberStats['total_match_entries']
            : 0.0;

        $highlights = [
            'favorite_activity' => $activityBreakdown[0] ?? null,
            'best_score' => $memberStats['best_score'],
            'last_session' => $sessionSummaries[0]['session'] ?? null,
        ];

        $isOwnProfile = $currentUser->getId() === $member->getId();

        return $this->render('member/profile.html.twig', [
            'group' => $group,
            'member' => $member,
            'memberRole' => $memberRole,
            'joinedAt' => $joinedAt,
            'isOwnProfile' => $isOwnProfile,
            'stats' => $memberStats,
            'recentForm' => array_slice($recentForm, 0, 5),
            'activityBreakdown' => $activityBreakdown,
            'sessionSummaries' => $sessionSummaries,
            'highlights' => $highlights,
            'allTimeRecordClaims' => $allTimeRecordClaims,
            'seasonRecordClaims' => $seasonRecordClaims,
            'seasonHonors' => $seasonHonors,
            'seasonOptions' => $seasonOptions,
            'selectedSeason' => $selectedSeason,
        ]);
    }

    /**
     * @param array<string, mixed> $memberStats
     * @param array<int, array<string, mixed>> $activityBreakdown
     * @param array<int, array{code:string,label:string,activityName:string,playedAt:\DateTimeImmutable}> $recentForm
     * @param array<int, array<int, array{playedAt:\DateTimeImmutable, homeKey:string, awayKey:string, homeScore:int, awayScore:int}>> $activityMatchEvents
     * @param array<int, array<string, mixed>> $activityRankingPools
     * @return array<string, mixed>|null
     */
    private function buildSessionSummary(
        Session $session,
        User $member,
        array &$memberStats,
        array &$activityBreakdown,
        array &$recentForm,
        array &$activityMatchEvents,
        array &$activityRankingPools,
    ): ?array {
        $activity = $session->getActivity();
        if (!$activity instanceof Activity) {
            return null;
        }

        $activityKey = (int) $activity->getId();
        if (!isset($activityBreakdown[$activityKey])) {
            $activityBreakdown[$activityKey] = [
                'activity_id' => $activity->getId(),
                'activity_name' => $activity->getName(),
                'context_mode' => $activity->getContextMode()->value,
                'sessions' => [],
                'score_entries_count' => 0,
                'match_entries_count' => 0,
                'total_score' => 0.0,
                'best_score' => null,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'last_played_at' => null,
            ];
        }

        if (!isset($activityRankingPools[$activityKey])) {
            $activityRankingPools[$activityKey] = [
                'activity_id' => $activity->getId(),
                'activity_name' => $activity->getName(),
                'context_mode' => $activity->getContextMode()->value,
                'participants' => [],
                'events' => [],
            ];
        }

        $participationItems = [];
        $scoreEntriesInSession = 0;
        $matchEntriesInSession = 0;
        $sessionScoreTotal = 0.0;
        $winsInSession = 0;
        $drawsInSession = 0;
        $lossesInSession = 0;

        foreach ($session->getEntries() as $entry) {
            if ('score_simple' === $entry->getType()->value) {
                foreach ($entry->getScores() as $score) {
                    $participantKey = $this->buildParticipantKey($score->getUser(), (string) $score->getParticipantName());
                    if (!isset($activityRankingPools[$activityKey]['participants'][$participantKey])) {
                        $activityRankingPools[$activityKey]['participants'][$participantKey] = [
                            'label' => $score->getUser()?->getDisplayName() ?: $score->getUser()?->getEmail() ?: (string) $score->getParticipantName(),
                            'pointsTotal' => 0.0,
                            'entriesCount' => 0,
                            'bestScore' => null,
                        ];
                    }

                    $scoreValue = (float) $score->getScore();
                    $activityRankingPools[$activityKey]['participants'][$participantKey]['pointsTotal'] += $scoreValue;
                    ++$activityRankingPools[$activityKey]['participants'][$participantKey]['entriesCount'];
                    $activityRankingPools[$activityKey]['participants'][$participantKey]['bestScore'] = null === $activityRankingPools[$activityKey]['participants'][$participantKey]['bestScore']
                        ? $scoreValue
                        : max((float) $activityRankingPools[$activityKey]['participants'][$participantKey]['bestScore'], $scoreValue);

                    if (null === $score->getUser() || $score->getUser()->getId() !== $member->getId()) {
                        continue;
                    }

                    ++$memberStats['total_score_entries'];
                    ++$scoreEntriesInSession;
                    $memberStats['total_scores'] += $scoreValue;
                    $sessionScoreTotal += $scoreValue;
                    $memberStats['best_score'] = null === $memberStats['best_score']
                        ? $scoreValue
                        : max((float) $memberStats['best_score'], $scoreValue);

                    ++$activityBreakdown[$activityKey]['score_entries_count'];
                    $activityBreakdown[$activityKey]['total_score'] += $scoreValue;
                    $activityBreakdown[$activityKey]['best_score'] = null === $activityBreakdown[$activityKey]['best_score']
                        ? $scoreValue
                        : max((float) $activityBreakdown[$activityKey]['best_score'], $scoreValue);

                    $participationItems[] = [
                        'kind' => 'score',
                        'badge' => 'Score',
                        'label' => $entry->getLabel() ?: 'Tour sans libellé',
                        'value' => sprintf('%s points', $this->formatNumber($scoreValue)),
                        'meta' => sprintf('Activité %s', $activity->getName()),
                    ];
                }

                continue;
            }

            if ('match' !== $entry->getType()->value || null === $entry->getEntryMatch()) {
                continue;
            }

            $match = $entry->getEntryMatch();

            $homeKey = $this->buildParticipantKey($match->getHomeUser(), (string) $match->getHomeName());
            $awayKey = $this->buildParticipantKey($match->getAwayUser(), (string) $match->getAwayName());
            $activityMatchEvents[$activityKey][] = [
                'playedAt' => $session->getPlayedAt(),
                'homeKey' => $homeKey,
                'awayKey' => $awayKey,
                'homeScore' => (int) $match->getHomeScore(),
                'awayScore' => (int) $match->getAwayScore(),
            ];
            $activityRankingPools[$activityKey]['events'][] = [
                'playedAt' => $session->getPlayedAt(),
                'homeKey' => $homeKey,
                'awayKey' => $awayKey,
                'homeScore' => (int) $match->getHomeScore(),
                'awayScore' => (int) $match->getAwayScore(),
            ];

            foreach ([
                [$homeKey, $match->getHomeUser()?->getDisplayName() ?: $match->getHomeUser()?->getEmail() ?: (string) $match->getHomeName(), (int) $match->getHomeScore(), (int) $match->getAwayScore()],
                [$awayKey, $match->getAwayUser()?->getDisplayName() ?: $match->getAwayUser()?->getEmail() ?: (string) $match->getAwayName(), (int) $match->getAwayScore(), (int) $match->getHomeScore()],
            ] as [$participantKey, $label, $scored, $conceded]) {
                if (!isset($activityRankingPools[$activityKey]['participants'][$participantKey])) {
                    $activityRankingPools[$activityKey]['participants'][$participantKey] = [
                        'label' => $label,
                        'wins' => 0,
                        'draws' => 0,
                        'losses' => 0,
                        'scored' => 0,
                        'conceded' => 0,
                    ];
                }

                $activityRankingPools[$activityKey]['participants'][$participantKey]['scored'] += $scored;
                $activityRankingPools[$activityKey]['participants'][$participantKey]['conceded'] += $conceded;

                if ($scored > $conceded) {
                    ++$activityRankingPools[$activityKey]['participants'][$participantKey]['wins'];
                } elseif ($scored < $conceded) {
                    ++$activityRankingPools[$activityKey]['participants'][$participantKey]['losses'];
                } else {
                    ++$activityRankingPools[$activityKey]['participants'][$participantKey]['draws'];
                }
            }

            $matchParticipation = $this->buildMatchParticipation($match, $member, $activity->getName());
            if (null === $matchParticipation) {
                continue;
            }

            ++$memberStats['total_match_entries'];
            ++$matchEntriesInSession;
            ++$activityBreakdown[$activityKey]['match_entries_count'];

            if ('V' === $matchParticipation['result']) {
                ++$memberStats['wins'];
                ++$winsInSession;
                ++$activityBreakdown[$activityKey]['wins'];
            } elseif ('N' === $matchParticipation['result']) {
                ++$memberStats['draws'];
                ++$drawsInSession;
                ++$activityBreakdown[$activityKey]['draws'];
            } else {
                ++$memberStats['losses'];
                ++$lossesInSession;
                ++$activityBreakdown[$activityKey]['losses'];
            }

            $recentForm[] = [
                'code' => $matchParticipation['result'],
                'label' => $matchParticipation['resultLabel'],
                'activityName' => $activity->getName(),
                'playedAt' => $session->getPlayedAt(),
            ];

            $participationItems[] = [
                'kind' => 'match',
                'badge' => $matchParticipation['result'],
                'label' => $entry->getLabel() ?: 'Match',
                'value' => $matchParticipation['summary'],
                'meta' => $matchParticipation['meta'],
            ];
        }

        if ($participationItems === []) {
            return null;
        }

        $activityBreakdown[$activityKey]['sessions'][$session->getId()] = true;
        $activityBreakdown[$activityKey]['last_played_at'] = $session->getPlayedAt();

        $headline = [];
        if ($scoreEntriesInSession > 0) {
            $headline[] = sprintf('%s pts saisis', $this->formatNumber($sessionScoreTotal));
        }

        if ($matchEntriesInSession > 0) {
            $headline[] = sprintf('%dV · %dN · %dD', $winsInSession, $drawsInSession, $lossesInSession);
        }

        return [
            'session' => $session,
            'activity' => $activity,
            'headline' => $headline !== [] ? implode(' · ', $headline) : 'Participation enregistrée',
            'items' => $participationItems,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $activityRankingPools
     * @return array<int, array{activity_id:int,activity_name:string,rank:int,label:string,context_mode:string,value:string}>
     */
    private function buildSeasonHonors(array $activityRankingPools, string $memberKey): array
    {
        $honors = [];

        foreach ($activityRankingPools as $pool) {
            $participants = $pool['participants'] ?? [];
            if (!isset($participants[$memberKey])) {
                continue;
            }

            if ('ranking' === $pool['context_mode']) {
                $rows = [];
                foreach ($participants as $participant) {
                    $rows[] = [
                        'label' => (string) $participant['label'],
                        'pointsTotal' => (float) $participant['pointsTotal'],
                        'entriesCount' => (int) $participant['entriesCount'],
                    ];
                }

                usort($rows, static fn (array $left, array $right): int => [$right['pointsTotal'], $left['label']] <=> [$left['pointsTotal'], $right['label']]);

                $memberLabel = (string) $participants[$memberKey]['label'];
                foreach ($rows as $index => $row) {
                    if ($row['label'] !== $memberLabel) {
                        continue;
                    }

                    $rank = $index + 1;
                    if ($rank <= 3) {
                        $honors[] = [
                            'activity_id' => (int) $pool['activity_id'],
                            'activity_name' => (string) $pool['activity_name'],
                            'rank' => $rank,
                            'label' => sprintf('Top %d', $rank),
                            'context_mode' => (string) $pool['context_mode'],
                            'value' => sprintf('%s pts', $this->formatNumber((float) $participants[$memberKey]['pointsTotal'])),
                        ];
                    }

                    break;
                }

                continue;
            }

            $eloRatings = $this->eloRatingService->calculateFromEvents(
                $pool['events'] ?? [],
                1000,
                24.0,
                (string) ($pool['activity_name'] ?? ''),
                (string) ($pool['context_mode'] ?? ''),
            );
            $rows = [];
            foreach ($participants as $key => $participant) {
                $rows[] = [
                    'key' => $key,
                    'label' => (string) $participant['label'],
                    'mmr' => $eloRatings[$key] ?? 1000,
                    'wins' => (int) ($participant['wins'] ?? 0),
                ];
            }

            usort($rows, static fn (array $left, array $right): int => [$right['mmr'], $right['wins'], $left['label']] <=> [$left['mmr'], $left['wins'], $right['label']]);

            foreach ($rows as $index => $row) {
                if ($row['key'] !== $memberKey) {
                    continue;
                }

                $rank = $index + 1;
                if ($rank <= 3) {
                    $honors[] = [
                        'activity_id' => (int) $pool['activity_id'],
                        'activity_name' => (string) $pool['activity_name'],
                        'rank' => $rank,
                        'label' => sprintf('Top %d', $rank),
                        'context_mode' => (string) $pool['context_mode'],
                        'value' => sprintf('%d MMR', (int) round((float) $row['mmr'])),
                    ];
                }

                break;
            }
        }

        usort($honors, static fn (array $left, array $right): int => [$left['rank'], $left['activity_name']] <=> [$right['rank'], $right['activity_name']]);

        return $honors;
    }

    /**
     * @param Session[] $sessions
     * @return array<int, array<string, mixed>>
     */
    private function buildRankingPoolsFromSessions(array $sessions): array
    {
        $memberStats = [];
        $activityBreakdown = [];
        $recentForm = [];
        $activityMatchEvents = [];
        $activityRankingPools = [];

        $dummyMember = new User();

        foreach ($sessions as $session) {
            $this->buildSessionSummary($session, $dummyMember, $memberStats, $activityBreakdown, $recentForm, $activityMatchEvents, $activityRankingPools);
        }

        return $activityRankingPools;
    }

    /**
     * @param array<int, array<string, mixed>> $activityRankingPools
     * @return array<int, array{activity_id:int,activity_name:string,title:string,value:string,scope:string}>
     */
    private function buildMemberRecordClaims(array $activityRankingPools, string $memberKey): array
    {
        $claims = [];

        foreach ($activityRankingPools as $pool) {
            $participants = $pool['participants'] ?? [];
            if (!isset($participants[$memberKey])) {
                continue;
            }

            if ('ranking' === $pool['context_mode']) {
                $leader = $participants;
                uasort($leader, static fn (array $left, array $right): int => [$right['pointsTotal'], $left['label']] <=> [$left['pointsTotal'], $right['label']]);
                if (array_key_first($leader) === $memberKey) {
                    $claims[] = [
                        'activity_id' => (int) $pool['activity_id'],
                        'activity_name' => (string) $pool['activity_name'],
                        'title' => 'Leader cumulé',
                        'value' => sprintf('%s pts', $this->formatNumber((float) $participants[$memberKey]['pointsTotal'])),
                        'scope' => (string) $pool['context_mode'],
                    ];
                }

                $bestScore = $participants;
                uasort($bestScore, static fn (array $left, array $right): int => [$right['bestScore'], $left['label']] <=> [$left['bestScore'], $right['label']]);
                if (array_key_first($bestScore) === $memberKey) {
                    $claims[] = [
                        'activity_id' => (int) $pool['activity_id'],
                        'activity_name' => (string) $pool['activity_name'],
                        'title' => 'Record de score',
                        'value' => sprintf('%s pts', $this->formatNumber((float) $participants[$memberKey]['bestScore'])),
                        'scope' => (string) $pool['context_mode'],
                    ];
                }

                continue;
            }

            $eloRatings = $this->eloRatingService->calculateFromEvents(
                $pool['events'] ?? [],
                1000,
                24.0,
                (string) ($pool['activity_name'] ?? ''),
                (string) ($pool['context_mode'] ?? ''),
            );
            arsort($eloRatings);
            if (array_key_first($eloRatings) === $memberKey) {
                $claims[] = [
                    'activity_id' => (int) $pool['activity_id'],
                    'activity_name' => (string) $pool['activity_name'],
                    'title' => 'Leader MMR',
                    'value' => sprintf('%d MMR', (int) round((float) ($eloRatings[$memberKey] ?? 1000))),
                    'scope' => (string) $pool['context_mode'],
                ];
            }

            $wins = $participants;
            uasort($wins, static fn (array $left, array $right): int => [$right['wins'], $left['label']] <=> [$left['wins'], $right['label']]);
            if (array_key_first($wins) === $memberKey) {
                $claims[] = [
                    'activity_id' => (int) $pool['activity_id'],
                    'activity_name' => (string) $pool['activity_name'],
                    'title' => 'Plus de victoires',
                    'value' => sprintf('%d victoire%s', (int) $participants[$memberKey]['wins'], (int) $participants[$memberKey]['wins'] > 1 ? 's' : ''),
                    'scope' => (string) $pool['context_mode'],
                ];
            }
        }

        usort($claims, static fn (array $left, array $right): int => [$left['activity_name'], $left['title']] <=> [$right['activity_name'], $right['title']]);

        return $claims;
    }

    /**
     * @return array{result:string,resultLabel:string,summary:string,meta:string}|null
     */
    private function buildMatchParticipation(EntryMatch $match, User $member, string $activityName): ?array
    {
        $memberId = $member->getId();
        $isHome = null !== $match->getHomeUser() && $match->getHomeUser()->getId() === $memberId;
        $isAway = null !== $match->getAwayUser() && $match->getAwayUser()->getId() === $memberId;

        if (!$isHome && !$isAway) {
            return null;
        }

        $memberScore = $isHome ? (int) $match->getHomeScore() : (int) $match->getAwayScore();
        $opponentScore = $isHome ? (int) $match->getAwayScore() : (int) $match->getHomeScore();
        $memberName = $isHome ? (string) $match->getHomeName() : (string) $match->getAwayName();
        $opponentName = $isHome ? (string) $match->getAwayName() : (string) $match->getHomeName();

        $result = 'N';
        $resultLabel = 'Match nul';
        if ($memberScore > $opponentScore) {
            $result = 'V';
            $resultLabel = 'Victoire';
        } elseif ($memberScore < $opponentScore) {
            $result = 'D';
            $resultLabel = 'Défaite';
        }

        return [
            'result' => $result,
            'resultLabel' => $resultLabel,
            'summary' => sprintf('%s %d - %d %s', $memberName, $memberScore, $opponentScore, $opponentName),
            'meta' => sprintf('%s sur %s', $resultLabel, $activityName),
        ];
    }

    private function buildParticipantKey(?User $user, string $fallbackName): string
    {
        if ($user instanceof User && null !== $user->getId()) {
            return 'user-' . $user->getId();
        }

        return 'name-' . mb_strtolower(trim($fallbackName));
    }

    private function formatNumber(float $number): string
    {
        if (abs($number - round($number)) < 0.00001) {
            return number_format($number, 0, ',', ' ');
        }

        return number_format($number, 1, ',', ' ');
    }

    /**
     * @param Session[] $sessions
     * @return array<string, string>
     */
    private function buildSeasonOptionsFromSessions(array $sessions): array
    {
        $options = ['all' => 'Toutes saisons'];

        foreach ($sessions as $session) {
            $playedAt = $session->getPlayedAt();
            if (null === $playedAt) {
                continue;
            }

            $key = $playedAt->format('Y-m');
            $options[$key] = $this->formatSeasonLabel($key);
        }

        return $options;
    }

    /**
     * @param array<string, string> $seasonOptions
     */
    private function resolveSeasonFilter(string $seasonFilter, array $seasonOptions): string
    {
        if (isset($seasonOptions[$seasonFilter])) {
            return $seasonFilter;
        }

        return 'all';
    }

    /**
     * @param Session[] $sessions
     * @return Session[]
     */
    private function filterSessionsBySeason(array $sessions, string $seasonFilter): array
    {
        if ('all' === $seasonFilter) {
            return $sessions;
        }

        return array_values(array_filter(
            $sessions,
            static fn (Session $session): bool => $session->getPlayedAt()?->format('Y-m') === $seasonFilter,
        ));
    }

    private function formatSeasonLabel(string $season): string
    {
        [$year, $month] = explode('-', $season);
        $seasonCode = sprintf('S%d-%s', (int) $month, $year);

        $months = [
            '01' => 'Janvier',
            '02' => 'Février',
            '03' => 'Mars',
            '04' => 'Avril',
            '05' => 'Mai',
            '06' => 'Juin',
            '07' => 'Juillet',
            '08' => 'Août',
            '09' => 'Septembre',
            '10' => 'Octobre',
            '11' => 'Novembre',
            '12' => 'Décembre',
        ];

        return sprintf('%s · %s %s', $seasonCode, $months[$month] ?? $month, $year);
    }
}
