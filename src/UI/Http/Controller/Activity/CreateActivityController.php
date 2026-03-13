<?php

namespace App\UI\Http\Controller\Activity;

use App\Application\Activity\CreateActivity\CreateActivityCommand;
use App\Application\Activity\CreateActivity\CreateActivityHandler;
use App\Domain\Activity\ContextMode;
use App\Entity\GameGroup;
use App\Entity\User;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateActivityController extends AbstractController
{
    public function __construct(
        private readonly CreateActivityHandler $handler,
    ) {
    }

    #[Route('/groups/{id}/activities/create', name: 'activity_create', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, GameGroup $group): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $this->denyAccessUnlessGranted(GroupVoter::MANAGE, $group);

        if ($request->isMethod('POST')) {
            $name = $request->request->getString('name');

            if ($name === '') {
                $this->addFlash('error', 'Le nom de l\'activité est obligatoire.');
                return $this->redirectToRoute('activity_create', ['id' => $group->getId()]);
            }

            try {
                $contextModeValue = $request->request->getString('contextMode', ContextMode::RANKING->value);
                $contextMode = ContextMode::tryFrom($contextModeValue) ?? ContextMode::RANKING;

                $command = new CreateActivityCommand(
                    groupId: $group->getId(),
                    name: $name,
                    creatorUserId: $user->getId(),
                    contextMode: $contextMode,
                );

                $result = $this->handler->handle($command);

                $this->addFlash('success', sprintf(
                    'L\'activité "%s" a été créée avec succès.',
                    $name
                ));

                return $this->redirectToRoute('activity_list', ['id' => $group->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('activity_create', ['id' => $group->getId()]);
            }
        }

        return $this->render('activity/create.html.twig', [
            'group' => $group,
        ]);
    }
}
