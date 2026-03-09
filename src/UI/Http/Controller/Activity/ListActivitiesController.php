<?php

namespace App\UI\Http\Controller\Activity;

use App\Entity\GameGroup;
use App\Entity\User;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListActivitiesController extends AbstractController
{
    #[Route('/groups/{id}/activities', name: 'activity_list', methods: ['GET'])]
    public function __invoke(GameGroup $group): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);

        $activities = $group->getActivities();

        return $this->render('activity/list.html.twig', [
            'group' => $group,
            'activities' => $activities,
        ]);
    }
}
