<?php

namespace App\UI\Http\Controller\Session;

use App\Application\Session\CreateSession\CreateSessionCommand;
use App\Application\Session\CreateSession\CreateSessionHandler;
use App\Entity\GameGroup;
use App\Entity\User;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateSessionController extends AbstractController
{
    public function __construct(
        private readonly CreateSessionHandler $handler,
    ) {
    }

    #[Route('/groups/{id}/sessions/create', name: 'session_create', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, GameGroup $group): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted(GroupVoter::MANAGE, $group);

        $activities = $group->getActivities();
        $preferredActivityId = 0;

        if ($request->isMethod('GET')) {
            $requestedActivityId = $request->query->getInt('activity');
            if ($requestedActivityId > 0) {
                foreach ($activities as $activity) {
                    if ($activity->getId() === $requestedActivityId) {
                        $preferredActivityId = $requestedActivityId;
                        break;
                    }
                }
            }
        }

        if ($request->isMethod('POST')) {
            $activityId = $request->request->getInt('activityId');
            $title = $request->request->getString('title', '');
            $playedAtStr = $request->request->getString('playedAt');

            if ($activityId === 0) {
                $this->addFlash('error', 'Veuillez sélectionner une activité.');
                return $this->redirectToRoute('session_create', ['id' => $group->getId()]);
            }

            if ($playedAtStr === '') {
                $this->addFlash('error', 'La date de jeu est obligatoire.');
                return $this->redirectToRoute('session_create', ['id' => $group->getId()]);
            }

            try {
                $playedAt = new \DateTimeImmutable($playedAtStr);

                $command = new CreateSessionCommand(
                    groupId: $group->getId(),
                    activityId: $activityId,
                    creatorUserId: $user->getId(),
                    playedAt: $playedAt,
                    title: $title !== '' ? $title : null,
                );

                $result = $this->handler->handle($command);

                $this->addFlash('success', 'Session créée avec succès.');

                return $this->redirectToRoute('session_show', [
                    'groupId' => $group->getId(),
                    'sessionId' => $result->getSessionId(),
                ]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('session_create', ['id' => $group->getId()]);
            }
        }

        return $this->render('session/create.html.twig', [
            'group' => $group,
            'activities' => $activities,
            'preferredActivityId' => $preferredActivityId,
        ]);
    }
}
