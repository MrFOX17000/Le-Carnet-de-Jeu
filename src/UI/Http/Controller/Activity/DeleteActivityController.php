<?php

namespace App\UI\Http\Controller\Activity;

use App\Entity\Activity;
use App\Entity\User;
use App\Security\Voter\GroupVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteActivityController extends AbstractController
{
    #[Route('/groups/{groupId}/activities/{activityId}/delete', name: 'activity_delete', methods: ['POST'])]
    public function __invoke(Request $request, int $groupId, int $activityId, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $activity = $entityManager->getRepository(Activity::class)->find($activityId);

        if (!$activity instanceof Activity) {
            throw new NotFoundHttpException('Activity not found.');
        }

        $group = $activity->getGroup();

        if (null === $group || $group->getId() !== $groupId) {
            throw new NotFoundHttpException('Activity does not belong to this group.');
        }

        $this->denyAccessUnlessGranted(GroupVoter::MANAGE, $group);

        if (!$this->isCsrfTokenValid('delete-activity-'.$activity->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide pour la suppression de l\'activité.');

            return $this->redirectToRoute('activity_list', [
                'id' => $groupId,
            ]);
        }

        if ($activity->getSessions()->count() > 0) {
            $this->addFlash('error', 'Impossible de supprimer une activité déjà utilisée dans une ou plusieurs sessions.');

            return $this->redirectToRoute('activity_list', [
                'id' => $groupId,
            ]);
        }

        $activityName = $activity->getName() ?? 'cette activité';

        $entityManager->remove($activity);
        $entityManager->flush();

        $this->addFlash('success', sprintf('L\'activité %s a été supprimée.', $activityName));

        return $this->redirectToRoute('activity_list', [
            'id' => $groupId,
        ]);
    }
}