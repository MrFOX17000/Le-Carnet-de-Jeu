<?php

namespace App\UI\Http\Controller\Invite;

use App\Entity\Invite;
use App\Entity\User;
use App\Security\Voter\GroupVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class RevokeInviteController extends AbstractController
{
    #[Route('/groups/{groupId}/invites/{inviteId}/revoke', name: 'invite_revoke', methods: ['POST'])]
    public function __invoke(Request $request, int $groupId, int $inviteId, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $invite = $entityManager->getRepository(Invite::class)->find($inviteId);

        if (!$invite instanceof Invite) {
            throw new NotFoundHttpException('Invitation not found.');
        }

        $group = $invite->getGroup();

        if ($group->getId() !== $groupId) {
            throw new NotFoundHttpException('Invitation does not belong to this group.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::MANAGE, $group);

        if (!$this->isCsrfTokenValid('revoke-invite-'.$invite->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide pour la révocation de l\'invitation.');

            return $this->redirectToRoute('group_show', [
                'id' => $groupId,
            ]);
        }

        if (null !== $invite->getAcceptedAt()) {
            $this->addFlash('error', 'Cette invitation a déjà été acceptée et ne peut plus être révoquée.');

            return $this->redirectToRoute('group_show', [
                'id' => $groupId,
            ]);
        }

        $email = $invite->getEmail();

        $entityManager->remove($invite);
        $entityManager->flush();

        $this->addFlash('success', sprintf('L\'invitation envoyée à %s a été révoquée.', $email));

        return $this->redirectToRoute('group_show', [
            'id' => $groupId,
        ]);
    }
}