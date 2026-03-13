<?php

namespace App\UI\Http\Controller\Group;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListGroupController extends AbstractController
{
    #[Route('/groups', name: 'group_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $groupsData = [];

        foreach ($user->getGroupMembers() as $membership) {
            $group = $membership->getGroup();

            $sessions = $group->getSessions()->toArray();
            usort($sessions, static fn ($a, $b) => $b->getPlayedAt() <=> $a->getPlayedAt());

            $lastPlayedAt = $sessions !== [] ? $sessions[0]->getPlayedAt() : null;

            $activityCounts = [];
            foreach ($sessions as $session) {
                $actName = $session->getActivity()->getName();
                $activityCounts[$actName] = ($activityCounts[$actName] ?? 0) + 1;
            }
            arsort($activityCounts);
            $topActivity = array_key_first($activityCounts);

            $groupsData[] = [
                'membership'     => $membership,
                'group'          => $group,
                'lastPlayedAt'   => $lastPlayedAt,
                'sessionsCount'  => count($sessions),
                'activitiesCount' => $group->getActivities()->count(),
                'topActivity'    => $topActivity,
            ];
        }

        // Trier : groupes avec session récente en premier
        usort($groupsData, static function (array $a, array $b): int {
            if ($a['lastPlayedAt'] === null && $b['lastPlayedAt'] === null) {
                return 0;
            }
            if ($a['lastPlayedAt'] === null) {
                return 1;
            }
            if ($b['lastPlayedAt'] === null) {
                return -1;
            }

            return $b['lastPlayedAt'] <=> $a['lastPlayedAt'];
        });

        return $this->render('group/index.html.twig', [
            'groupsData' => $groupsData,
        ]);
    }
}