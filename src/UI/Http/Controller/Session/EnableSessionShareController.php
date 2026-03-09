<?php

namespace App\UI\Http\Controller\Session;

use App\Application\Session\EnableSessionShare\EnableSessionShareCommand;
use App\Application\Session\EnableSessionShare\EnableSessionShareHandler;
use App\Entity\User;
use App\Repository\GameGroupRepository;
use App\Repository\SessionRepository;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class EnableSessionShareController extends AbstractController
{
    public function __construct(
        private readonly EnableSessionShareHandler $handler,
        private readonly SessionRepository $sessionRepository,
        private readonly GameGroupRepository $groupRepository,
    ) {
    }

    #[Route(
        '/groups/{groupId}/sessions/{sessionId}/share',
        name: 'session_enable_share',
        methods: ['POST']
    )]
    public function __invoke(int $groupId, int $sessionId): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Charger le groupe
        $group = $this->groupRepository->find($groupId);
        if (null === $group) {
            throw new NotFoundHttpException('Group not found.');
        }

        // Charger la session
        $session = $this->sessionRepository->find($sessionId);
        if (null === $session) {
            throw new NotFoundHttpException('Session not found.');
        }

        // Vérifier que la session appartient effectivement au groupe
        if ($session->getGroup()->getId() !== $group->getId()) {
            throw new NotFoundHttpException('Session does not belong to this group.');
        }

        // Vérifier que l'utilisateur a les droits de gestion
        $this->denyAccessUnlessGranted(GroupVoter::MANAGE, $group);

        try {
            $command = new EnableSessionShareCommand(
                sessionId: $session->getId() ?? 0,
                groupId: $group->getId(),
                userIdRequestingShare: $user->getId(),
            );

            $result = $this->handler->handle($command);

            $this->addFlash('success', 'Session sharing enabled! Link copied to clipboard.');

            // Rediriger vers le détail de la session
            return $this->redirectToRoute('session_show', [
                'groupId' => $groupId,
                'sessionId' => $result->getSessionId(),
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('session_show', [
                'groupId' => $groupId,
                'sessionId' => $sessionId,
            ]);
        }
    }
}
