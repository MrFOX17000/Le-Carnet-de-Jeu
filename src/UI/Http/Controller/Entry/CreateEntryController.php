<?php

namespace App\UI\Http\Controller\Entry;

use App\Application\Entry\CreateEntry\CreateEntryCommand;
use App\Application\Entry\CreateEntry\CreateEntryHandler;
use App\Domain\Entry\EntryType;
use App\Entity\GameGroup;
use App\Entity\User;
use App\Repository\SessionRepository;
use App\Security\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CreateEntryController extends AbstractController
{
    #[Route('/groups/{id}/sessions/{sessionId}/entries/create', name: 'entry_create', methods: ['GET', 'POST'])]
    #[IsGranted(GroupVoter::MANAGE, 'group')]
    public function __invoke(
        Request $request,
        GameGroup $group,
        int $sessionId,
        SessionRepository $sessionRepository,
        CreateEntryHandler $handler,
    ): Response {
        $session = $sessionRepository->find($sessionId);
        if (null === $session || $session->getGroup()->getId() !== $group->getId()) {
            throw $this->createAccessDeniedException('Session not found');
        }

        $activity = $session->getActivity();

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ('POST' === $request->getMethod()) {
            $data = $request->request->all();
            
            // Parse scores from request
            $scores = [];
            $participantNames = $data['participant_names'] ?? [];
            $scoreValues = $data['scores'] ?? [];
            $participantUserIds = $data['participant_user_ids'] ?? [];
            
            foreach ($participantNames as $idx => $participantName) {
                if (!empty($participantName) && isset($scoreValues[$idx])) {
                    $participantUserId = null;
                    if (isset($participantUserIds[$idx]) && '' !== (string) $participantUserIds[$idx]) {
                        $participantUserId = (int) $participantUserIds[$idx];
                    }

                    $scores[] = [
                        'participantName' => $participantName,
                        'score' => (float) $scoreValues[$idx],
                        'userId' => $participantUserId,
                    ];
                }
            }

            $command = new CreateEntryCommand(
                sessionId: $session->getId(),
                groupId: $group->getId(),
                creatorUserId: $user->getId(),
                type: EntryType::SCORE_SIMPLE,
                label: !empty($data['label']) ? $data['label'] : null,
                scores: $scores,
            );

            $result = $handler->handle($command);

            return $this->redirectToRoute('session_show', [
                'groupId' => $group->getId(),
                'sessionId' => $result->sessionId,
            ]);
        }

        return $this->render('entry/create.html.twig', [
            'group' => $group,
            'session' => $session,
            'activity' => $activity,
            'groupMembers' => $group->getGroupMembers(),
        ]);
    }
}
