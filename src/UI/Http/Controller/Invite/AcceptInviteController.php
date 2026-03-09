<?php

namespace App\UI\Http\Controller\Invite;

use App\Application\Invite\AcceptInvite\AcceptInviteCommand;
use App\Application\Invite\AcceptInvite\AcceptInviteHandler;
use App\Entity\User;
use App\Repository\InviteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AcceptInviteController extends AbstractController
{
    public function __construct(
        private readonly InviteRepository $inviteRepository,
        private readonly AcceptInviteHandler $handler,
    ) {
    }

    #[Route('/invites/{token}', name: 'invite_accept', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, string $token): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Chercher l'invitation
        $invite = $this->inviteRepository->findOneBy(['token' => $token]);

        if (null === $invite) {
            $this->addFlash('error', 'Invitation not found or token is invalid.');
            return $this->redirectToRoute('group_index');
        }

        if ($request->isMethod('POST')) {
            try {
                $command = new AcceptInviteCommand(
                    token: $token,
                    userId: $user->getId(),
                );

                $result = $this->handler->handle($command);

                $this->addFlash('success', 'You have successfully joined the group!');

                return $this->redirectToRoute('group_show', [
                    'id' => $result->getGroupId(),
                ]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('group_index');
            }
        }

        // GET: Afficher la page de confirmation
        return $this->render('invite/accept.html.twig', [
            'invite' => $invite,
        ]);
    }
}
