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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ResendInviteController extends AbstractController
{
    #[Route('/groups/{groupId}/invites/{inviteId}/resend', name: 'invite_resend', methods: ['POST'])]
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

        if (!$this->isCsrfTokenValid('resend-invite-'.$invite->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide pour le renvoi de l\'invitation.');

            return $this->redirectToRoute('group_show', [
                'id' => $groupId,
            ]);
        }

        if (null !== $invite->getAcceptedAt()) {
            $this->addFlash('error', 'Cette invitation a déjà été acceptée. Aucun nouveau lien n\'est nécessaire.');

            return $this->redirectToRoute('group_show', [
                'id' => $groupId,
            ]);
        }

        $invite->setExpiresAt(new \DateTimeImmutable());

        $newInvite = new Invite($invite->getRole());
        $newInvite->setGroup($group);
        $newInvite->setCreatedBy($user);
        $newInvite->setEmail($invite->getEmail());
        $newInvite->setToken(bin2hex(random_bytes(32)));
        $newInvite->setExpiresAt(new \DateTimeImmutable('+7 days'));

        $group->addInvite($newInvite);
        $user->addInvite($newInvite);

        $entityManager->persist($newInvite);
        $entityManager->flush();

        $inviteUrl = $this->generateUrl('invite_accept', [
            'token' => $newInvite->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->addFlash('success', sprintf(
            'Nouvelle invitation générée pour %s. Nouveau lien : %s',
            $newInvite->getEmail(),
            $inviteUrl
        ));

        return $this->redirectToRoute('group_show', [
            'id' => $groupId,
        ]);
    }
}