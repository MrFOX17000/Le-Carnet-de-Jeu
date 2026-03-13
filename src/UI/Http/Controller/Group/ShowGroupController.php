<?php

namespace App\UI\Http\Controller\Group;

use App\Entity\GameGroup;
use App\Entity\Invite;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Voter\GroupVoter;

class ShowGroupController extends AbstractController
{
    #[Route('/groups/{id}', name: 'group_show', methods: ['GET'])]
    public function __invoke(GameGroup $group): Response
    {
        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);
        $canManage = $this->isGranted(GroupVoter::MANAGE, $group);

        $sessions = $group->getSessions()->toArray();
        usort(
            $sessions,
            static fn ($left, $right): int => $right->getPlayedAt() <=> $left->getPlayedAt()
        );

        $activityFilters = [];

        foreach ($sessions as $session) {
            $activityName = $session->getActivity()?->getName() ?? 'Sans activité';
            $activityFilters[$activityName] = $activityName;
        }

        ksort($activityFilters, \SORT_NATURAL | \SORT_FLAG_CASE);

        $recentSessions = array_slice($sessions, 0, 8);
        $archivedSessions = array_slice($sessions, 8);

        $activitySessionCounts = [];
        foreach ($sessions as $session) {
            $activityName = $session->getActivity()?->getName() ?? 'Sans activité';
            $activitySessionCounts[$activityName] = ($activitySessionCounts[$activityName] ?? 0) + 1;
        }

        arsort($activitySessionCounts, \SORT_NUMERIC);
        $topActivityName = array_key_first($activitySessionCounts);
        $topActivitySessions = $topActivityName !== null ? ($activitySessionCounts[$topActivityName] ?? 0) : 0;

        $now = new \DateTimeImmutable();
        $pendingInvites = array_values(array_filter(
            $group->getInvites()->toArray(),
            static fn (Invite $invite): bool => null === $invite->getAcceptedAt() && $invite->getExpiresAt() > $now
        ));

        $pastInvites = array_values(array_filter(
            $group->getInvites()->toArray(),
            static fn (Invite $invite): bool => null !== $invite->getAcceptedAt() || $invite->getExpiresAt() <= $now
        ));

        usort(
            $pendingInvites,
            static fn (Invite $left, Invite $right): int => $left->getExpiresAt() <=> $right->getExpiresAt()
        );

        usort(
            $pastInvites,
            static function (Invite $left, Invite $right): int {
                $leftDate = $left->getAcceptedAt() ?? $left->getExpiresAt();
                $rightDate = $right->getAcceptedAt() ?? $right->getExpiresAt();

                return $rightDate <=> $leftDate;
            }
        );

        $nextStep = null;
        if ($canManage) {
            if ($group->getActivities()->count() === 0) {
                $nextStep = [
                    'label' => 'Créer la première activité',
                    'route' => 'activity_create',
                    'params' => ['id' => $group->getId()],
                ];
            } elseif ($sessions === []) {
                $nextStep = [
                    'label' => 'Créer la première session',
                    'route' => 'session_create',
                    'params' => ['id' => $group->getId()],
                ];
            } elseif ($recentSessions !== []) {
                $nextStep = [
                    'label' => 'Continuer sur la dernière session',
                    'route' => 'session_show',
                    'params' => [
                        'groupId' => $group->getId(),
                        'sessionId' => $recentSessions[0]->getId(),
                    ],
                ];
            }
        }

        $groupOverview = [
            'membersCount' => $group->getGroupMembers()->count(),
            'activitiesCount' => $group->getActivities()->count(),
            'sessionsCount' => count($sessions),
            'pendingInvitesCount' => count($pendingInvites),
            'lastPlayedAt' => $sessions !== [] ? $sessions[0]->getPlayedAt() : null,
            'topActivityName' => $topActivityName,
            'topActivitySessions' => $topActivitySessions,
            'nextStep' => $nextStep,
        ];
        
        return $this->render('group/show.html.twig', [
            'group' => $group,
            'canManage' => $canManage,
            'sessions' => $sessions,
            'recentSessions' => $recentSessions,
            'archivedSessions' => $archivedSessions,
            'activityFilters' => array_values($activityFilters),
            'pendingInvites' => $pendingInvites,
            'pastInvites' => $pastInvites,
            'groupOverview' => $groupOverview,
        ]);
    }
}