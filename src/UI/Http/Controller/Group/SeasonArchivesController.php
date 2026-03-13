<?php

namespace App\UI\Http\Controller\Group;

use App\Domain\Activity\ContextMode;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\Session;
use App\Entity\User;
use App\Repository\SessionRepository;
use App\Security\Voter\GroupVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SeasonArchivesController extends AbstractController
{
    #[Route('/groups/{id<\d+>}/seasons', name: 'group_seasons', methods: ['GET'])]
    public function __invoke(
        Request $request,
        int $id,
        SessionRepository $sessionRepository,
        #[MapEntity(id: 'id')]
        GameGroup $group,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);

        if ($group->getId() !== $id) {
            throw new NotFoundHttpException('Groupe introuvable.');
        }

        $sessions = $sessionRepository->findByGroupIdsWithEntries([$group->getId()]);
        $archives = $this->buildArchives($sessions);
        $seasonOptions = $this->buildSeasonOptions($archives);
        $selectedSeason = $this->resolveSeasonSelection($request->query->getString('season', ''), $seasonOptions, 0);
        $compareSeason = $this->resolveSeasonSelection($request->query->getString('compare', ''), $seasonOptions, 1, $selectedSeason);

        return $this->render('group/seasons.html.twig', [
            'group' => $group,
            'archives' => $archives,
            'seasonOptions' => $seasonOptions,
            'selectedSeason' => $selectedSeason,
            'compareSeason' => $compareSeason,
            'comparisonView' => $this->buildComparisonView($archives, $selectedSeason, $compareSeason),
        ]);
    }

    /**
     * @param Session[] $sessions
     * @return array<int, array{seasonKey:string,seasonLabel:string,sessionsCount:int,activitiesCount:int,participantsCount:int,activities:array<int, array<string, mixed>>}>
     */
    private function buildArchives(array $sessions): array
    {
        $seasons = [];

        foreach ($sessions as $session) {
            $playedAt = $session->getPlayedAt();
            $activity = $session->getActivity();
            if (!$playedAt instanceof \DateTimeImmutable || !$activity instanceof Activity || null === $activity->getId()) {
                continue;
            }

            $seasonKey = $playedAt->format('Y-m');
            if (!isset($seasons[$seasonKey])) {
                $seasons[$seasonKey] = [
                    'seasonKey' => $seasonKey,
                    'seasonLabel' => $this->formatSeasonLabel($seasonKey),
                    'sessionsCount' => 0,
                    'activities' => [],
                    'participants' => [],
                ];
            }

            ++$seasons[$seasonKey]['sessionsCount'];

            $activityId = (int) $activity->getId();
            if (!isset($seasons[$seasonKey]['activities'][$activityId])) {
                $seasons[$seasonKey]['activities'][$activityId] = [
                    'activityId' => $activityId,
                    'activityName' => $activity->getName(),
                    'contextMode' => $activity->getContextMode()->value,
                    'sessions' => [],
                ];
            }

            $seasons[$seasonKey]['activities'][$activityId]['sessions'][] = $session;

            foreach ($session->getEntries() as $entry) {
                if ($activity->getContextMode() === ContextMode::RANKING) {
                    foreach ($entry->getScores() as $score) {
                        $key = $this->buildParticipantKey($score->getUser(), (string) $score->getParticipantName());
                        $seasons[$seasonKey]['participants'][$key] = true;
                    }

                    continue;
                }

                $match = $entry->getEntryMatch();
                if (null === $match) {
                    continue;
                }

                $seasons[$seasonKey]['participants'][$this->buildParticipantKey($match->getHomeUser(), (string) $match->getHomeName())] = true;
                $seasons[$seasonKey]['participants'][$this->buildParticipantKey($match->getAwayUser(), (string) $match->getAwayName())] = true;
            }
        }

        krsort($seasons);
        $orderedSeasonKeys = array_keys($seasons);
        $rows = [];

        foreach ($orderedSeasonKeys as $index => $seasonKey) {
            $season = $seasons[$seasonKey];
            $previousSeason = isset($orderedSeasonKeys[$index + 1]) ? $seasons[$orderedSeasonKeys[$index + 1]] : null;
            $activities = [];
            foreach ($season['activities'] as $activityArchive) {
                $mode = ContextMode::from($activityArchive['contextMode']);
                $leaderboard = $this->buildLeaderboard($mode, $activityArchive['sessions']);
                $comparison = null;

                if (null !== $previousSeason && isset($previousSeason['activities'][$activityArchive['activityId']])) {
                    $previousActivity = $previousSeason['activities'][$activityArchive['activityId']];
                    $previousLeaderboard = $this->buildLeaderboard($mode, $previousActivity['sessions']);
                    $comparison = $this->buildActivityComparison($mode, $leaderboard, $previousLeaderboard, count($activityArchive['sessions']), count($previousActivity['sessions']), $previousSeason['seasonLabel']);
                }

                $activities[] = [
                    'activityId' => $activityArchive['activityId'],
                    'activityName' => $activityArchive['activityName'],
                    'contextMode' => $mode->value,
                    'leaderboard' => array_slice($leaderboard, 0, 3),
                    'records' => $this->buildRecords($mode, $leaderboard),
                    'sessionsCount' => count($activityArchive['sessions']),
                    'comparison' => $comparison,
                ];
            }

            usort($activities, static fn (array $left, array $right): int => [$right['sessionsCount'], $left['activityName']] <=> [$left['sessionsCount'], $right['activityName']]);

            $rows[] = [
                'seasonKey' => $season['seasonKey'],
                'seasonLabel' => $season['seasonLabel'],
                'sessionsCount' => $season['sessionsCount'],
                'activitiesCount' => count($activities),
                'participantsCount' => count($season['participants']),
                'comparison' => $this->buildSeasonComparison($season, $previousSeason),
                'activities' => $activities,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $archives
     * @return array<string, string>
     */
    private function buildSeasonOptions(array $archives): array
    {
        $options = [];

        foreach ($archives as $season) {
            $options[(string) $season['seasonKey']] = (string) $season['seasonLabel'];
        }

        return $options;
    }

    /**
     * @param array<string, string> $seasonOptions
     */
    private function resolveSeasonSelection(string $value, array $seasonOptions, int $fallbackIndex = 0, ?string $exclude = null): ?string
    {
        if (isset($seasonOptions[$value]) && $value !== $exclude) {
            return $value;
        }

        $keys = array_keys($seasonOptions);
        foreach ($keys as $index => $key) {
            if ($index < $fallbackIndex || $key === $exclude) {
                continue;
            }

            return $key;
        }

        foreach ($keys as $key) {
            if ($key !== $exclude) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $archives
     * @return array<string, mixed>|null
     */
    private function buildComparisonView(array $archives, ?string $selectedSeason, ?string $compareSeason): ?array
    {
        if (null === $selectedSeason || null === $compareSeason || $selectedSeason === $compareSeason) {
            return null;
        }

        $archiveMap = [];
        foreach ($archives as $archive) {
            $archiveMap[$archive['seasonKey']] = $archive;
        }

        if (!isset($archiveMap[$selectedSeason], $archiveMap[$compareSeason])) {
            return null;
        }

        $current = $archiveMap[$selectedSeason];
        $compare = $archiveMap[$compareSeason];
        $currentActivities = [];
        foreach ($current['activities'] as $activity) {
            $currentActivities[$activity['activityId']] = $activity;
        }

        $compareActivities = [];
        foreach ($compare['activities'] as $activity) {
            $compareActivities[$activity['activityId']] = $activity;
        }

        $activityIds = array_unique([...array_keys($currentActivities), ...array_keys($compareActivities)]);
        $activityComparisons = [];

        foreach ($activityIds as $activityId) {
            $left = $currentActivities[$activityId] ?? null;
            $right = $compareActivities[$activityId] ?? null;
            $activityName = $left['activityName'] ?? $right['activityName'] ?? 'Activité';
            $contextMode = $left['contextMode'] ?? $right['contextMode'] ?? ContextMode::RANKING->value;
            $leftLeader = $left['leaderboard'][0]['label'] ?? null;
            $rightLeader = $right['leaderboard'][0]['label'] ?? null;

            $metricLabel = 'Classement';
            $metricDelta = 0;

            if ('ranking' === $contextMode) {
                $metricLabel = 'pts leader';
                $metricDelta = (int) round((float) ($left['leaderboard'][0]['pointsTotal'] ?? 0) - (float) ($right['leaderboard'][0]['pointsTotal'] ?? 0));
            } else {
                $metricLabel = 'victoires leader';
                $metricDelta = (int) ($left['leaderboard'][0]['wins'] ?? 0) - (int) ($right['leaderboard'][0]['wins'] ?? 0);
            }

            $activityComparisons[] = [
                'activityId' => $activityId,
                'activityName' => $activityName,
                'contextMode' => $contextMode,
                'currentLeader' => $leftLeader,
                'compareLeader' => $rightLeader,
                'leaderChanged' => $leftLeader !== $rightLeader,
                'currentSessions' => (int) ($left['sessionsCount'] ?? 0),
                'compareSessions' => (int) ($right['sessionsCount'] ?? 0),
                'sessionsDelta' => (int) ($left['sessionsCount'] ?? 0) - (int) ($right['sessionsCount'] ?? 0),
                'metricLabel' => $metricLabel,
                'metricDelta' => $metricDelta,
                'currentExists' => null !== $left,
                'compareExists' => null !== $right,
            ];
        }

        usort($activityComparisons, static fn (array $left, array $right): int => [$right['currentSessions'], $left['activityName']] <=> [$left['currentSessions'], $right['activityName']]);

        return [
            'currentSeasonLabel' => $current['seasonLabel'],
            'compareSeasonLabel' => $compare['seasonLabel'],
            'sessionsDelta' => (int) $current['sessionsCount'] - (int) $compare['sessionsCount'],
            'participantsDelta' => (int) $current['participantsCount'] - (int) $compare['participantsCount'],
            'activitiesDelta' => (int) $current['activitiesCount'] - (int) $compare['activitiesCount'],
            'activityComparisons' => $activityComparisons,
        ];
    }

    /**
     * @param array<string, mixed>|null $previousSeason
     * @return array<string, int|string>|null
     */
    private function buildSeasonComparison(array $season, ?array $previousSeason): ?array
    {
        if (null === $previousSeason) {
            return null;
        }

        return [
            'label' => sprintf('vs %s', $previousSeason['seasonLabel']),
            'sessionsDelta' => (int) $season['sessionsCount'] - (int) $previousSeason['sessionsCount'],
            'participantsDelta' => count($season['participants']) - count($previousSeason['participants']),
            'activitiesDelta' => count($season['activities']) - count($previousSeason['activities']),
        ];
    }

    /**
     * @param array<int, array<string, int|float|string>> $leaderboard
     * @param array<int, array<string, int|float|string>> $previousLeaderboard
     * @return array<string, int|string>|null
     */
    private function buildActivityComparison(ContextMode $mode, array $leaderboard, array $previousLeaderboard, int $sessionsCount, int $previousSessionsCount, string $previousSeasonLabel): ?array
    {
        if ([] === $leaderboard || [] === $previousLeaderboard) {
            return null;
        }

        $comparison = [
            'label' => sprintf('vs %s', $previousSeasonLabel),
            'sessionsDelta' => $sessionsCount - $previousSessionsCount,
            'leaderChanged' => (string) $leaderboard[0]['label'] !== (string) $previousLeaderboard[0]['label'] ? 'oui' : 'non',
            'leader' => (string) $leaderboard[0]['label'],
            'previousLeader' => (string) $previousLeaderboard[0]['label'],
        ];

        if ($mode === ContextMode::RANKING) {
            $comparison['metricDelta'] = (int) round((float) $leaderboard[0]['pointsTotal'] - (float) $previousLeaderboard[0]['pointsTotal']);
            $comparison['metricLabel'] = 'pts leader';

            return $comparison;
        }

        $comparison['metricDelta'] = (int) $leaderboard[0]['wins'] - (int) $previousLeaderboard[0]['wins'];
        $comparison['metricLabel'] = 'victoires leader';

        return $comparison;
    }

    /**
     * @param Session[] $sessions
     * @return array<int, array<string, int|float|string>>
     */
    private function buildLeaderboard(ContextMode $mode, array $sessions): array
    {
        $participants = [];

        foreach ($sessions as $session) {
            foreach ($session->getEntries() as $entry) {
                if ($mode === ContextMode::RANKING) {
                    foreach ($entry->getScores() as $score) {
                        $key = $this->buildParticipantKey($score->getUser(), (string) $score->getParticipantName());
                        if (!isset($participants[$key])) {
                            $participants[$key] = [
                                'label' => $this->resolveParticipantLabel($score->getUser(), (string) $score->getParticipantName()),
                                'pointsTotal' => 0.0,
                                'entriesCount' => 0,
                                'bestScore' => null,
                            ];
                        }

                        $scoreValue = (float) $score->getScore();
                        $participants[$key]['pointsTotal'] += $scoreValue;
                        ++$participants[$key]['entriesCount'];
                        $participants[$key]['bestScore'] = null === $participants[$key]['bestScore']
                            ? $scoreValue
                            : max((float) $participants[$key]['bestScore'], $scoreValue);
                    }

                    continue;
                }

                $match = $entry->getEntryMatch();
                if (null === $match) {
                    continue;
                }

                foreach ([
                    [$this->buildParticipantKey($match->getHomeUser(), (string) $match->getHomeName()), $this->resolveParticipantLabel($match->getHomeUser(), (string) $match->getHomeName()), (int) $match->getHomeScore(), (int) $match->getAwayScore()],
                    [$this->buildParticipantKey($match->getAwayUser(), (string) $match->getAwayName()), $this->resolveParticipantLabel($match->getAwayUser(), (string) $match->getAwayName()), (int) $match->getAwayScore(), (int) $match->getHomeScore()],
                ] as [$key, $label, $scored, $conceded]) {
                    if (!isset($participants[$key])) {
                        $participants[$key] = [
                            'label' => $label,
                            'wins' => 0,
                            'draws' => 0,
                            'losses' => 0,
                            'scored' => 0,
                        ];
                    }

                    $participants[$key]['scored'] += $scored;
                    if ($scored > $conceded) {
                        ++$participants[$key]['wins'];
                    } elseif ($scored < $conceded) {
                        ++$participants[$key]['losses'];
                    } else {
                        ++$participants[$key]['draws'];
                    }
                }
            }
        }

        if ($mode === ContextMode::RANKING) {
            $rows = array_values(array_map(static fn (array $participant): array => [
                'label' => (string) $participant['label'],
                'pointsTotal' => (float) $participant['pointsTotal'],
                'entriesCount' => (int) $participant['entriesCount'],
                'bestScore' => (float) ($participant['bestScore'] ?? 0.0),
            ], $participants));

            usort($rows, static fn (array $left, array $right): int => [$right['pointsTotal'], $right['bestScore'], $left['label']] <=> [$left['pointsTotal'], $left['bestScore'], $right['label']]);

            return $rows;
        }

        $rows = array_values(array_map(static fn (array $participant): array => [
            'label' => (string) $participant['label'],
            'wins' => (int) $participant['wins'],
            'draws' => (int) $participant['draws'],
            'losses' => (int) $participant['losses'],
            'scored' => (int) $participant['scored'],
        ], $participants));

        usort($rows, static fn (array $left, array $right): int => [$right['wins'], $right['scored'], $left['label']] <=> [$left['wins'], $left['scored'], $right['label']]);

        return $rows;
    }

    /**
     * @param array<int, array<string, int|float|string>> $leaderboard
     * @return array<int, array{title:string,value:string,meta:string}>
     */
    private function buildRecords(ContextMode $mode, array $leaderboard): array
    {
        if ([] === $leaderboard) {
            return [];
        }

        if ($mode === ContextMode::RANKING) {
            $bestScore = $leaderboard;
            usort($bestScore, static fn (array $left, array $right): int => [$right['bestScore'], $left['label']] <=> [$left['bestScore'], $right['label']]);

            return [
                [
                    'title' => 'Champion',
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

        $attack = $leaderboard;
        usort($attack, static fn (array $left, array $right): int => [$right['scored'], $left['label']] <=> [$left['scored'], $right['label']]);

        return [
            [
                'title' => 'Champion',
                'value' => (string) $leaderboard[0]['label'],
                'meta' => sprintf('%d victoire%s', (int) $leaderboard[0]['wins'], (int) $leaderboard[0]['wins'] > 1 ? 's' : ''),
            ],
            [
                'title' => 'Meilleure attaque',
                'value' => (string) $attack[0]['label'],
                'meta' => sprintf('%d points marqués', (int) $attack[0]['scored']),
            ],
        ];
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