<?php

namespace App\UI\Http\Controller\Entry;

use App\Application\Entry\CreateMatchEntry\CreateMatchEntryCommand;
use App\Application\Entry\CreateMatchEntry\CreateMatchEntryHandler;
use App\Entity\GameGroup;
use App\Entity\User;
use App\Repository\SessionRepository;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CreateMatchEntryController extends AbstractController
{
    #[Route('/groups/{id}/sessions/{sessionId}/entries/match/create', name: 'entry_match_create', methods: ['GET', 'POST'])]
    #[IsGranted(GroupVoter::MANAGE, 'group')]
    public function __invoke(
        Request $request,
        GameGroup $group,
        int $sessionId,
        SessionRepository $sessionRepository,
        CreateMatchEntryHandler $handler,
    ): Response {
        $session = $sessionRepository->find($sessionId);
        if (null === $session || $session->getGroup()->getId() !== $group->getId()) {
            throw $this->createAccessDeniedException('Session not found');
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ('POST' === $request->getMethod()) {
            $data = $request->request->all();

            try {
                $result = $handler->handle(new CreateMatchEntryCommand(
                    groupId: $group->getId(),
                    sessionId: $session->getId(),
                    creatorUserId: $user->getId(),
                    homeName: (string) ($data['homeName'] ?? ''),
                    awayName: (string) ($data['awayName'] ?? ''),
                    homeScore: (int) ($data['homeScore'] ?? -1),
                    awayScore: (int) ($data['awayScore'] ?? -1),
                    label: isset($data['label']) ? (string) $data['label'] : null,
                    homeUserId: isset($data['homeUserId']) && '' !== (string) $data['homeUserId'] ? (int) $data['homeUserId'] : null,
                    awayUserId: isset($data['awayUserId']) && '' !== (string) $data['awayUserId'] ? (int) $data['awayUserId'] : null,
                ));

                return $this->redirectToRoute('entry_show', [
                    'groupId' => $group->getId(),
                    'sessionId' => $result->sessionId,
                    'entryId' => $result->entryId,
                ]);
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('entry/match_create.html.twig', [
            'group' => $group,
            'session' => $session,
            'activity' => $session->getActivity(),
            'groupMembers' => $group->getGroupMembers(),
        ]);
    }
}