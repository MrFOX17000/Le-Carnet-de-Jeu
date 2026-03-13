<?php

namespace App\UI\Http\Controller\Invite;

use App\Application\Invite\CreateInvite\CreateInviteCommand;
use App\Application\Invite\CreateInvite\CreateInviteHandler;
use App\Domain\Group\GroupRole;
use App\Entity\GameGroup;
use App\Entity\User;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CreateInviteController extends AbstractController
{
    public function __construct(
        private readonly CreateInviteHandler $handler,
    ) {
    }

    #[Route('/groups/{id}/invites/create', name: 'invite_create', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, GameGroup $group): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted(GroupVoter::MANAGE, $group);

        if ($request->isMethod('POST')) {
            $email = $request->request->getString('email');

            if ($email === '') {
                $this->addFlash('error', 'L\'adresse e-mail est obligatoire.');

                return $this->redirectToRoute('invite_create', [
                    'id' => $group->getId(),
                ]);
            }

            try {
                $command = new CreateInviteCommand(
                    groupId: $group->getId(),
                    creatorUserId: $user->getId(),
                    email: $email,
                    role: GroupRole::MEMBER,
                );

                $result = $this->handler->handle($command);

                $inviteUrl = $this->generateUrl('invite_accept', [
                    'token' => $result->getToken(),
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $this->addFlash('success', sprintf(
                    'Invitation créée pour %s. Partagez ce lien d\'accès au groupe : %s',
                    $email,
                    $inviteUrl
                ));

                return $this->redirectToRoute('group_show', [
                    'id' => $group->getId(),
                ]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('invite_create', [
                    'id' => $group->getId(),
                ]);
            }
        }

        return $this->render('invite/create.html.twig', [
            'group' => $group,
        ]);
    }
}