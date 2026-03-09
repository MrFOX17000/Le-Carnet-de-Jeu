<?php

namespace App\UI\Http\Controller\Api\Group;

use App\Application\Api\Session\GetSessionDetails\GetSessionDetailsHandler;
use App\Application\Api\Session\GetSessionDetails\GetSessionDetailsQuery;
use App\Entity\User;
use App\Repository\GameGroupRepository;
use App\Security\Voter\GroupVoter;
use App\UI\Http\Response\JsonErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ShowSessionApiController extends AbstractController
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
        private readonly GetSessionDetailsHandler $getSessionDetailsHandler,
    ) {
    }

    #[Route('/api/groups/{groupId<\d+>}/sessions/{sessionId<\d+>}', name: 'api_group_session_show', methods: ['GET'])]
    public function __invoke(int $groupId, int $sessionId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return JsonErrorResponse::create('unauthorized', 401);
        }

        $group = $this->groupRepository->find($groupId);
        if ($group === null) {
            return JsonErrorResponse::create('not_found', 404);
        }

        if (!$this->isGranted(GroupVoter::VIEW, $group)) {
            return JsonErrorResponse::create('forbidden', 403);
        }

        $sessionDetail = $this->getSessionDetailsHandler->handle(new GetSessionDetailsQuery(
            sessionId: $sessionId,
            groupId: $groupId,
            userId: (int) $user->getId(),
        ));

        if ($sessionDetail === null) {
            return JsonErrorResponse::create('not_found', 404);
        }

        $entries = [];
        foreach ($sessionDetail->entries as $entry) {
            $entries[] = [
                'id' => $entry->id,
                'type' => $entry->type,
                'label' => $entry->label,
                'details' => $entry->details,
            ];
        }

        return $this->json([
            'data' => [
                'id' => $sessionDetail->id,
                'groupId' => $sessionDetail->groupId,
                'activityId' => $sessionDetail->activityId,
                'activityName' => $sessionDetail->activityName,
                'title' => $sessionDetail->title,
                'playedAt' => $sessionDetail->playedAt,
                'createdAt' => $sessionDetail->createdAt,
                'createdBy' => [
                    'id' => $sessionDetail->createdById,
                    'email' => $sessionDetail->createdByEmail,
                ],
                'entries' => $entries,
            ],
        ]);
    }
}
