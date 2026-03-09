<?php

namespace App\UI\Http\Controller\Group;

use App\Entity\GameGroup;
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

        $sessions = $group->getSessions()->toArray();
        usort(
            $sessions,
            static fn ($left, $right): int => $right->getPlayedAt() <=> $left->getPlayedAt()
        );
        
        return $this->render('group/show.html.twig', [
            'group' => $group,
            'sessions' => $sessions,
        ]);
    }
}