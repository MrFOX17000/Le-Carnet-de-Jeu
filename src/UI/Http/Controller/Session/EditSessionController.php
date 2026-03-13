<?php

namespace App\UI\Http\Controller\Session;

use App\Entity\Activity;
use App\Entity\Session;
use App\Entity\User;
use App\Security\Voter\GroupVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class EditSessionController extends AbstractController
{
    #[Route('/groups/{groupId}/sessions/{sessionId}/edit', name: 'session_edit', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, int $groupId, int $sessionId, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $session = $entityManager->getRepository(Session::class)->find($sessionId);

        if (!$session instanceof Session) {
            throw new NotFoundHttpException('Session not found.');
        }

        $group = $session->getGroup();

        if (null === $group || $group->getId() !== $groupId) {
            throw new NotFoundHttpException('Session does not belong to this group.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::MANAGE, $group);

        $activities = $group->getActivities()->toArray();

        if ($request->isMethod('POST')) {
            $activityId = $request->request->getInt('activityId');
            $title = trim($request->request->getString('title', ''));
            $playedAtStr = $request->request->getString('playedAt');

            if ($activityId === 0) {
                $this->addFlash('error', 'Vous devez sélectionner une activité.');

                return $this->redirectToRoute('session_edit', [
                    'groupId' => $groupId,
                    'sessionId' => $sessionId,
                ]);
            }

            if ($playedAtStr === '') {
                $this->addFlash('error', 'La date et l\'heure de la session sont obligatoires.');

                return $this->redirectToRoute('session_edit', [
                    'groupId' => $groupId,
                    'sessionId' => $sessionId,
                ]);
            }

            $selectedActivity = null;

            foreach ($activities as $activity) {
                if ($activity instanceof Activity && $activity->getId() === $activityId) {
                    $selectedActivity = $activity;
                    break;
                }
            }

            if (!$selectedActivity instanceof Activity) {
                $this->addFlash('error', 'Cette activité n\'appartient pas à ce groupe.');

                return $this->redirectToRoute('session_edit', [
                    'groupId' => $groupId,
                    'sessionId' => $sessionId,
                ]);
            }

            try {
                $playedAt = new \DateTimeImmutable($playedAtStr);
            } catch (\Exception) {
                $this->addFlash('error', 'Le format de date est invalide.');

                return $this->redirectToRoute('session_edit', [
                    'groupId' => $groupId,
                    'sessionId' => $sessionId,
                ]);
            }

            $session->setActivity($selectedActivity);
            $session->setTitle($title !== '' ? $title : null);
            $session->setPlayedAt($playedAt);

            $entityManager->flush();

            $this->addFlash('success', 'La session a été mise à jour.');

            return $this->redirectToRoute('session_show', [
                'groupId' => $groupId,
                'sessionId' => $sessionId,
            ]);
        }

        return $this->render('session/create.html.twig', [
            'group' => $group,
            'activities' => $activities,
            'isEdit' => true,
            'currentSession' => $session,
        ]);
    }
}