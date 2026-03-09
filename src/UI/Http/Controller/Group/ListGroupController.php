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

        return $this->render('group/index.html.twig', [
            'memberships' => $user->getGroupMembers(),
        ]);
    }
}