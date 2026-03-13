<?php

namespace App\UI\Http\Controller\Activity;

use App\Application\Rating\EloRatingService;
use App\Domain\Activity\ContextMode;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\Session;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\SessionRepository;
use App\Security\Voter\GroupVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ShowActivityController extends AbstractController
{
    #[Route('/groups/{groupId<\d+>}/activities/{activityId<\d+>}', name: 'activity_show', methods: ['GET'])]
    public function __invoke(
        Request $request,
        int $groupId,
        int $activityId,
        ActivityRepository $activityRepository,
        SessionRepository $sessionRepository,
        EloRatingService $eloRatingService,
        #[MapEntity(id: 'groupId')]
        GameGroup $group,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);

        if ($group->getId() !== $groupId) {
            throw new NotFoundHttpException('Groupe introuvable.');
        }

        $activity = $activityRepository->find($activityId);

        if (!$activity instanceof Activity || $activity->getGroup()?->getId() !== $group->getId()) {
            throw new NotFoundHttpException('Activité introuvable.');
        }

        $sessions = $sessionRepository->findByActivityWithEntries($activity->getId());

        $seasonFilter = trim($request->query->getString('season', 'all'));
        $availableSeasons = $this->buildSeasonOptionsFromSessions($sessions);
        $selectedSeason = $this->resolveSeasonFilter($seasonFilter, $availableSeasons);
        $filteredSessions = $this->filterSessionsBySeason($sessions, $selectedSeason);
        $selectedSeasonLabel = $availableSeasons[$selectedSeason] ?? 'Toutes saisons';

        $allTimeTracker = $this->buildTracker($activity, $sessions, $eloRatingService);
        $tracker = 'all' === $selectedSeason
            ? $allTimeTracker
            : $this->buildTracker($activity, $filteredSessions, $eloRatingService);
        $canManage = $this->isGranted(GroupVoter::MANAGE, $group);
        $seasonTopThree = $selectedSeason !== 'all' ? array_slice($tracker['leaderboard'], 0, 3) : [];
        $recordsSeasonKey = $this->resolveRecordsSeasonKey($selectedSeason, $availableSeasons);
        $recordsSeasonLabel = null;
        $seasonRecords = [];

        $entriesInSelection = array_reduce(
            $filteredSessions,
            static fn (int $carry, Session $session): int => $carry + $session->getEntries()->count(),
            0,
        );

        $activityOverview = [
            'sessionsInSelection' => count($filteredSessions),
            'entriesInSelection' => $entriesInSelection,
            'participantsInSelection' => count($tracker['leaderboard']),
            'isFilteredSeason' => $selectedSeason !== 'all',
        ];

        $quickActions = [];
        if ($canManage) {
            $quickActions[] = [
                'label' => 'Créer une session',
                'route' => 'session_create',
                'params' => ['id' => $group->getId(), 'activity' => $activity->getId()],
                'variant' => 'primary',
            ];

            $latestSummary = $tracker['sessionSummaries'][0] ?? null;
            if (is_array($latestSummary) && isset($latestSummary['session']) && $latestSummary['session'] instanceof Session && null !== $latestSummary['session']->getId()) {
                $quickActions[] = [
                    'label' => $activity->getContextMode()->isMatchBased() ? 'Ajouter un match' : 'Ajouter un tour',
                    'route' => $activity->getContextMode()->isMatchBased() ? 'entry_match_create' : 'entry_create',
                    'params' => ['id' => $group->getId(), 'sessionId' => $latestSummary['session']->getId()],
                    'variant' => 'secondary',
                ];
            }
        }

        if ($selectedSeason !== 'all') {
            $quickActions[] = [
                'label' => 'Voir toutes les saisons',
                'route' => 'activity_show',
                'params' => ['groupId' => $group->getId(), 'activityId' => $activity->getId(), 'season' => 'all'],
                'variant' => 'secondary',
            ];
        }

        if (null !== $recordsSeasonKey && isset($availableSeasons[$recordsSeasonKey])) {
            $recordsSeasonLabel = $availableSeasons[$recordsSeasonKey];
            $seasonTracker = $recordsSeasonKey === $selectedSeason
                ? $tracker
                : $this->buildTracker($activity, $this->filterSessionsBySeason($sessions, $recordsSeasonKey), $eloRatingService);
            $seasonRecords = $this->buildRecords($activity, $seasonTracker);
        }

        return $this->render('activity/show.html.twig', [
            'group' => $group,
            'activity' => $activity,
            'canManage' => $canManage,
            'activityStats' => $tracker['activityStats'],
            'activityOverview' => $activityOverview,
            'quickActions' => $quickActions,
            'highlights' => $tracker['highlights'],
            'leaderboard' => $tracker['leaderboard'],
            'sessionSummaries' => $tracker['sessionSummaries'],
            'isMatchMode' => $activity->getContextMode()->isMatchBased(),
            'allTimeRecords' => $this->buildRecords($activity, $allTimeTracker),
            'seasonRecords' => $seasonRecords,
            'seasonOptions' => $availableSeasons,
            'selectedSeason' => $selectedSeason,
            'selectedSeasonLabel' => $selectedSeasonLabel,
            'recordsSeasonLabel' => $recordsSeasonLabel,
            'recordsSeasonUsesSelection' => null !== $recordsSeasonKey && $recordsSeasonKey === $selectedSeason,
            'seasonTopThree' => $seasonTopThree,
        ]);
    }

    /**
     * @param array{leaderboard: array<int, array<string, int|float|string>>} $tracker
     * @return array<int, array{title:string, value:string, meta:string}>
     */
    private function buildRecords(Activity $activity, array $tracker): array
    {
        $leaderboard = $tracker['leaderboard'];
        if ([] === $leaderboard) {
            return [];
        }

        if ($activity->getContextMode() === ContextMode::RANKING) {
            $leader = $leaderboard[0];
            $bestScoreHolder = $leaderboard;
            usort($bestScoreHolder, static fn (array $left, array $right): int => [$right['bestScore'], $left['label']] <=> [$left['bestScore'], $right['label']]);
            $volumeHolder = $leaderboard;
            usort($volumeHolder, static fn (array $left, array $right): int => [$right['entriesCount'], $right['sessionsPlayed'], $left['label']] <=> [$left['entriesCount'], $left['sessionsPlayed'], $right['label']]);

            return [
                [
                    'title' => 'Leader cumulé',
                    'value' => (string) $leader['label'],
                    'meta' => sprintf('%s pts cumulés', self::formatNumber((float) $leader['pointsTotal'])),
                ],
                [
                    'title' => 'Record de score',
                    'value' => sprintf('%s pts', self::formatNumber((float) $bestScoreHolder[0]['bestScore'])),
                    'meta' => (string) $bestScoreHolder[0]['label'],
                ],
                [
                    'title' => 'Volume de jeu',
                    'value' => (string) $volumeHolder[0]['label'],
                    'meta' => sprintf('%d entrée%s sur %d session%s', (int) $volumeHolder[0]['entriesCount'], (int) $volumeHolder[0]['entriesCount'] > 1 ? 's' : '', (int) $volumeHolder[0]['sessionsPlayed'], (int) $volumeHolder[0]['sessionsPlayed'] > 1 ? 's' : ''),
                ],
            ];
        }

        $leader = $leaderboard[0];
        $winsHolder = $leaderboard;
        usort($winsHolder, static fn (array $left, array $right): int => [$right['wins'], $right['winRate'], $left['label']] <=> [$left['wins'], $left['winRate'], $right['label']]);
        $attackHolder = $leaderboard;
        usort($attackHolder, static fn (array $left, array $right): int => [$right['scored'], $right['scoreDiff'], $left['label']] <=> [$left['scored'], $left['scoreDiff'], $right['label']]);

        return [
            [
                'title' => 'Leader MMR',
                'value' => (string) $leader['label'],
                'meta' => sprintf('%d MMR', (int) $leader['mmr']),
            ],
            [
                'title' => 'Plus de victoires',
                'value' => (string) $winsHolder[0]['label'],
                'meta' => sprintf('%d victoire%s', (int) $winsHolder[0]['wins'], (int) $winsHolder[0]['wins'] > 1 ? 's' : ''),
            ],
            [
                'title' => 'Meilleure attaque',
                'value' => (string) $attackHolder[0]['label'],
                'meta' => sprintf('%d points marqués', (int) $attackHolder[0]['scored']),
            ],
        ];
    }

    /**
     * @param Session[] $sessions
     * @return array{
     *   activityStats: array<int, array{label:string, value:string, meta:string}>,
     *   highlights: array<int, array{title:string, value:string, meta:string}>,
     *   leaderboard: array<int, array<string, int|float|string>>,
     *   sessionSummaries: array<int, array{session:Session, entriesCount:int, headline:string, meta:string}>
     * }
     */
    private function buildTracker(Activity $activity, array $sessions, EloRatingService $eloRatingService): array
    {
        $mode = $activity->getContextMode();
        $participants = [];
        $entriesCount = 0;
        $bestSessionScore = null;
        $largestWin = null;
        $sessionSummaries = [];
        $matchEvents = [];

        foreach ($sessions as $session) {
            $entries = $session->getEntries()->toArray();
            $entriesCount += count($entries);
            $headline = 'Aucun résultat saisi';
            $meta = sprintf('%d entrée%s', count($entries), count($entries) > 1 ? 's' : '');
            $sessionPerformances = [];

            foreach ($entries as $entry) {
                if ($mode === ContextMode::RANKING) {
                    foreach ($entry->getScores() as $score) {
                        $participantKey = $this->buildParticipantKey($score->getUser(), (string) $score->getParticipantName());
                        $label = $this->resolveParticipantLabel($score->getUser(), (string) $score->getParticipantName());

                        if (!isset($participants[$participantKey])) {
                            $participants[$participantKey] = [
                                'key' => $participantKey,
                                'label' => $label,
                                'sessions' => [],
                                'entriesCount' => 0,
                                'pointsTotal' => 0.0,
                                'bestScore' => null,
                                'lastPlayedAt' => null,
                            ];
                        }

                        $numericScore = (float) $score->getScore();
                        $participants[$participantKey]['sessions'][$session->getId()] = true;
                        ++$participants[$participantKey]['entriesCount'];
                        $participants[$participantKey]['pointsTotal'] += $numericScore;
                        $participants[$participantKey]['bestScore'] = null === $participants[$participantKey]['bestScore']
                            ? $numericScore
                            : max((float) $participants[$participantKey]['bestScore'], $numericScore);
                        $participants[$participantKey]['lastPlayedAt'] = $session->getPlayedAt();
                        $sessionPerformances[] = [
                            'label' => $label,
                            'score' => $numericScore,
                        ];

                        if (null === $bestSessionScore || $numericScore > $bestSessionScore['score']) {
                            $bestSessionScore = [
                                'score' => $numericScore,
                                'label' => $label,
                                'sessionTitle' => $session->getTitle() ?: $activity->getName(),
                            ];
                        }
                    }

                    continue;
                }

                $match = $entry->getEntryMatch();
                if (null === $match) {
                    continue;
                }

                $homeKey = $this->buildParticipantKey($match->getHomeUser(), (string) $match->getHomeName());
                $awayKey = $this->buildParticipantKey($match->getAwayUser(), (string) $match->getAwayName());
                $homeLabel = $this->resolveParticipantLabel($match->getHomeUser(), (string) $match->getHomeName());
                $awayLabel = $this->resolveParticipantLabel($match->getAwayUser(), (string) $match->getAwayName());
                $homeScore = (int) $match->getHomeScore();
                $awayScore = (int) $match->getAwayScore();

                $matchEvents[] = [
                    'playedAt' => $session->getPlayedAt(),
                    'homeKey' => $homeKey,
                    'awayKey' => $awayKey,
                    'homeScore' => $homeScore,
                    'awayScore' => $awayScore,
                ];

                foreach ([
                    [$homeKey, $homeLabel, $homeScore, $awayScore],
                    [$awayKey, $awayLabel, $awayScore, $homeScore],
                ] as [$key, $label, $scored, $conceded]) {
                    if (!isset($participants[$key])) {
                        $participants[$key] = [
                            'key' => $key,
                            'label' => $label,
                            'sessions' => [],
                            'matchesCount' => 0,
                            'wins' => 0,
                            'draws' => 0,
                            'losses' => 0,
                            'scored' => 0,
                            'conceded' => 0,
                            'lastPlayedAt' => null,
                        ];
                    }

                    $participants[$key]['sessions'][$session->getId()] = true;
                    ++$participants[$key]['matchesCount'];
                    $participants[$key]['scored'] += $scored;
                    $participants[$key]['conceded'] += $conceded;
                    $participants[$key]['lastPlayedAt'] = $session->getPlayedAt();

                    if ($scored > $conceded) {
                        ++$participants[$key]['wins'];
                    } elseif ($scored < $conceded) {
                        ++$participants[$key]['losses'];
                    } else {
                        ++$participants[$key]['draws'];
                    }
                }

                $winnerLabel = $homeScore === $awayScore
                    ? 'Match nul'
                    : ($homeScore > $awayScore ? $homeLabel : $awayLabel);
                $headline = sprintf('%s %d - %d %s', $homeLabel, $homeScore, $awayScore, $awayLabel);
                $meta = $winnerLabel === 'Match nul'
                    ? 'Match serré sans vainqueur'
                    : sprintf('Victoire de %s', $winnerLabel);

                $margin = abs($homeScore - $awayScore);
                if (null === $largestWin || $margin > $largestWin['margin']) {
                    $largestWin = [
                        'margin' => $margin,
                        'headline' => $headline,
                    ];
                }
            }

            if ($mode === ContextMode::RANKING && $sessionPerformances !== []) {
                usort($sessionPerformances, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);
                $winner = $sessionPerformances[0];
                $headline = sprintf('%s mène la session avec %s pts', $winner['label'], self::formatNumber((float) $winner['score']));
                $meta = sprintf('%d participant%s scoré%s', count($sessionPerformances), count($sessionPerformances) > 1 ? 's' : '', count($sessionPerformances) > 1 ? 's' : '');
            }

            $sessionSummaries[] = [
                'session' => $session,
                'entriesCount' => count($entries),
                'headline' => $headline,
                'meta' => $meta,
            ];
        }

        $eloRatings = $mode === ContextMode::RANKING
            ? []
            : $eloRatingService->calculateFromEvents($matchEvents, 1000, 24.0, $activity->getName(), $mode->value);

        $leaderboard = $mode === ContextMode::RANKING
            ? $this->buildRankingLeaderboard($participants)
            : $this->buildMatchLeaderboard($participants, $eloRatings);

        $activityStats = [
            [
                'label' => 'Sessions',
                'value' => (string) count($sessions),
                'meta' => 'jouées sur cette activité',
            ],
            [
                'label' => 'Résultats saisis',
                'value' => (string) $entriesCount,
                'meta' => 'entrées cumulées',
            ],
            [
                'label' => 'Participants',
                'value' => (string) count($leaderboard),
                'meta' => 'joueurs ou équipes suivis',
            ],
            [
                'label' => 'Dernière session',
                'value' => $sessions !== [] ? $sessions[0]->getPlayedAt()?->format('d/m/Y') ?? '-' : '-',
                'meta' => $sessions !== [] ? ($sessions[0]->getTitle() ?: $activity->getName()) : 'pas encore jouée',
            ],
        ];

        $highlights = [];

        if ($leaderboard !== []) {
            $leader = $leaderboard[0];
            $highlights[] = [
                'title' => 'Leader actuel',
                'value' => (string) $leader['label'],
                'meta' => $mode === ContextMode::RANKING
                    ? sprintf('%s pts cumulés sur %d entrée%s', self::formatNumber((float) $leader['pointsTotal']), (int) $leader['entriesCount'], (int) $leader['entriesCount'] > 1 ? 's' : '')
                    : sprintf('%d victoire%s en %d match%s · MMR %d', (int) $leader['wins'], (int) $leader['wins'] > 1 ? 's' : '', (int) $leader['matchesCount'], (int) $leader['matchesCount'] > 1 ? 's' : '', (int) $leader['mmr']),
            ];

            $mostActive = $leaderboard;
            usort($mostActive, static function (array $left, array $right) use ($mode): int {
                $leftCount = $mode === ContextMode::RANKING ? (int) $left['sessionsPlayed'] : (int) $left['matchesCount'];
                $rightCount = $mode === ContextMode::RANKING ? (int) $right['sessionsPlayed'] : (int) $right['matchesCount'];

                return $rightCount <=> $leftCount;
            });
            $mostActiveLeader = $mostActive[0];
            $highlights[] = [
                'title' => 'Plus actif',
                'value' => (string) $mostActiveLeader['label'],
                'meta' => $mode === ContextMode::RANKING
                    ? sprintf('%d session%s trackée%s', (int) $mostActiveLeader['sessionsPlayed'], (int) $mostActiveLeader['sessionsPlayed'] > 1 ? 's' : '', (int) $mostActiveLeader['sessionsPlayed'] > 1 ? 's' : '')
                    : sprintf('%d match%s enregistrés', (int) $mostActiveLeader['matchesCount'], (int) $mostActiveLeader['matchesCount'] > 1 ? 's' : ''),
            ];
        }

        if ($mode === ContextMode::RANKING && null !== $bestSessionScore) {
            $highlights[] = [
                'title' => 'Meilleure perf',
                'value' => sprintf('%s pts', self::formatNumber((float) $bestSessionScore['score'])),
                'meta' => sprintf('%s sur %s', $bestSessionScore['label'], $bestSessionScore['sessionTitle']),
            ];
        }

        if ($mode->isMatchBased() && null !== $largestWin) {
            $highlights[] = [
                'title' => 'Plus gros écart',
                'value' => sprintf('+%d', (int) $largestWin['margin']),
                'meta' => $largestWin['headline'],
            ];
        }

        return [
            'activityStats' => $activityStats,
            'highlights' => $highlights,
            'leaderboard' => $leaderboard,
            'sessionSummaries' => $sessionSummaries,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $participants
     * @return array<int, array<string, int|float|string>>
     */
    private function buildRankingLeaderboard(array $participants): array
    {
        $rows = [];

        foreach ($participants as $participant) {
            $entriesCount = (int) $participant['entriesCount'];
            $sessionsPlayed = count((array) $participant['sessions']);
            $pointsTotal = (float) $participant['pointsTotal'];

            $rows[] = [
                'label' => (string) $participant['label'],
                'sessionsPlayed' => $sessionsPlayed,
                'entriesCount' => $entriesCount,
                'pointsTotal' => $pointsTotal,
                'averageScore' => $entriesCount > 0 ? $pointsTotal / $entriesCount : 0.0,
                'bestScore' => (float) ($participant['bestScore'] ?? 0.0),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return [$right['pointsTotal'], $right['averageScore'], $right['bestScore'], $left['label']]
                <=> [$left['pointsTotal'], $left['averageScore'], $left['bestScore'], $right['label']];
        });

        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
        }

        return $rows;
    }

    /**
     * @param array<string, array<string, mixed>> $participants
     * @param array<string, int> $eloRatings
     * @return array<int, array<string, int|float|string>>
     */
    private function buildMatchLeaderboard(array $participants, array $eloRatings): array
    {
        $rows = [];

        foreach ($participants as $participant) {
            $matchesCount = (int) $participant['matchesCount'];
            $wins = (int) $participant['wins'];
            $draws = (int) $participant['draws'];
            $losses = (int) $participant['losses'];
            $scored = (int) $participant['scored'];
            $conceded = (int) $participant['conceded'];
            $participantKey = (string) $participant['key'];
            $mmr = $eloRatings[$participantKey] ?? 1000;

            $rows[] = [
                'label' => (string) $participant['label'],
                'sessionsPlayed' => count((array) $participant['sessions']),
                'matchesCount' => $matchesCount,
                'wins' => $wins,
                'draws' => $draws,
                'losses' => $losses,
                'winRate' => $matchesCount > 0 ? ($wins * 100) / $matchesCount : 0.0,
                'scoreDiff' => $scored - $conceded,
                'scored' => $scored,
                'conceded' => $conceded,
                'mmr' => $mmr,
                'mmrDelta' => $mmr - 1000,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return [$right['mmr'], $right['wins'], $right['winRate'], $right['scoreDiff'], $right['scored'], $left['label']]
                <=> [$left['mmr'], $left['wins'], $left['winRate'], $left['scoreDiff'], $left['scored'], $right['label']];
        });

        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
        }

        return $rows;
    }

    private function buildParticipantKey(?User $user, string $fallbackName): string
    {
        if ($user instanceof User && null !== $user->getId()) {
            return 'user-' . $user->getId();
        }

        return 'name-' . mb_strtolower(trim($fallbackName));
    }

    private function resolveParticipantLabel(?User $user, string $fallbackName): string
    {
        if ($user instanceof User) {
            return $user->getDisplayName() ?: (string) $user->getEmail();
        }

        return $fallbackName;
    }

    private static function formatNumber(float $number): string
    {
        if (abs($number - round($number)) < 0.00001) {
            return number_format($number, 0, ',', ' ');
        }

        return number_format($number, 1, ',', ' ');
    }

    /**
     * @param array<string, string> $seasonOptions
     */
    private function resolveRecordsSeasonKey(string $selectedSeason, array $seasonOptions): ?string
    {
        if ('all' !== $selectedSeason && isset($seasonOptions[$selectedSeason])) {
            return $selectedSeason;
        }

        foreach ($seasonOptions as $key => $_label) {
            if ('all' !== $key) {
                return $key;
            }
        }

        return null;
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
