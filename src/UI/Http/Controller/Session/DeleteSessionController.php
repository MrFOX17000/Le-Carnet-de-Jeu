<?php

namespace App\UI\Http\Controller\Session;

use App\Entity\User;
use App\Repository\SessionRepository;
use App\Security\Voter\GroupVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteSessionController extends AbstractController
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
    ) {
    }

    #[Route('/groups/{groupId}/sessions/{sessionId}/delete', name: 'session_delete', methods: ['POST'])]
    public function __invoke(Request $request, int $groupId, int $sessionId, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $session = $this->sessionRepository->find($sessionId);

        if (null === $session) {
            throw new NotFoundHttpException('Session not found.');
        }

        $group = $session->getGroup();

        if (null === $group || $group->getId() !== $groupId) {
            throw new NotFoundHttpException('Session does not belong to this group.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::MANAGE, $group);

        if (!$this->isCsrfTokenValid('delete-session-'.$session->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide pour la suppression de la session.');

            return $this->redirectToRoute('session_show', [
                'groupId' => $groupId,
                'sessionId' => $sessionId,
            ]);
        }

        $sessionLabel = $session->getTitle() ?: sprintf(
            'Session %s du %s',
            $session->getActivity()?->getName() ?? 'sans activité',
            $session->getPlayedAt()?->format('d/m/Y') ?? '-'
        );

        $entityManager->remove($session);
        $entityManager->flush();

        $this->addFlash('success', sprintf('La session %s a été supprimée.', $sessionLabel));

        return $this->redirectToRoute('group_show', [
            'id' => $groupId,
        ]);
    }
}