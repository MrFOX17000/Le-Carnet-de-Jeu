<?php

namespace App\UI\Http\Controller\Activity;

use App\Domain\Activity\ContextMode;
use App\Entity\Activity;
use App\Entity\User;
use App\Security\Voter\GroupVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class EditActivityController extends AbstractController
{
    #[Route('/groups/{groupId}/activities/{activityId}/edit', name: 'activity_edit', methods: ['GET', 'POST'])]
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

        if ($request->isMethod('POST')) {
            $name = trim($request->request->getString('name'));

            if ($name === '') {
                $this->addFlash('error', 'Le nom de l\'activité est obligatoire.');

                return $this->redirectToRoute('activity_edit', [
                    'groupId' => $groupId,
                    'activityId' => $activityId,
                ]);
            }

            $activity->setName($name);

            $contextModeValue = trim($request->request->getString('contextMode', ''));
            $contextMode = ContextMode::tryFrom($contextModeValue) ?? $activity->getContextMode();
            $activity->setContextMode($contextMode);

            $entityManager->flush();

            $this->addFlash('success', sprintf('L\'activité %s a été mise à jour.', $name));

            return $this->redirectToRoute('activity_list', [
                'id' => $groupId,
            ]);
        }

        return $this->render('activity/create.html.twig', [
            'group' => $group,
            'isEdit' => true,
            'currentActivity' => $activity,
        ]);
    }
}