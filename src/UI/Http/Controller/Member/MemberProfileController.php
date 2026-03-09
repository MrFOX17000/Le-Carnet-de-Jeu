<?php

namespace App\UI\Http\Controller\Member;

use App\Entity\User;
use App\Repository\GameGroupRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class MemberProfileController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly GameGroupRepository $groupRepository,
        private readonly SessionRepository $sessionRepository,
    ) {
    }

    #[Route('/groups/{groupId}/members/{userId}', name: 'member_profile', methods: ['GET'])]
    public function __invoke(int $groupId, int $userId): Response
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
        foreach ($group->getGroupMembers() as $membership) {
            if ($membership->getUser()?->getId() === $member->getId()) {
                $isMemberOfGroup = true;
                break;
            }
        }

        if (!$isMemberOfGroup) {
            throw new NotFoundHttpException('Member is not part of this group.');
        }

        // Récupérer les sessions du groupe
        $sessions = $group->getSessions()->toArray();

        // Filtrer les sessions où le membre a participé
        $memberSessions = [];
        $memberStats = [
            'total_sessions' => 0,
            'total_score_entries' => 0,
            'total_match_entries' => 0,
            'total_scores' => 0,
        ];

        foreach ($sessions as $session) {
            $hasParticipation = false;

            foreach ($session->getEntries() as $entry) {
                if ('score_simple' === $entry->getType()->value) {
                    foreach ($entry->getScores() as $score) {
                        if (null !== $score->getUser() && $score->getUser()->getId() === $member->getId()) {
                            $hasParticipation = true;
                            $memberStats['total_score_entries']++;
                            $memberStats['total_scores'] += $score->getScore();
                        }
                    }
                } elseif ('match' === $entry->getType()->value && null !== $entry->getEntryMatch()) {
                    $match = $entry->getEntryMatch();
                    if ((null !== $match->getHomeUser() && $match->getHomeUser()->getId() === $member->getId()) ||
                        (null !== $match->getAwayUser() && $match->getAwayUser()->getId() === $member->getId())) {
                        $hasParticipation = true;
                        $memberStats['total_match_entries']++;
                    }
                }
            }

            if ($hasParticipation) {
                $memberSessions[] = $session;
                $memberStats['total_sessions']++;
            }
        }

        // Trier par playedAt DESC
        usort($memberSessions, function ($a, $b) {
            return $b->getPlayedAt() <=> $a->getPlayedAt();
        });

        return $this->render('member/profile.html.twig', [
            'group' => $group,
            'member' => $member,
            'sessions' => $memberSessions,
            'stats' => $memberStats,
        ]);
    }
}
