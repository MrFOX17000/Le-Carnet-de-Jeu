<?php

namespace App\UI\Http\Controller\Api\Group;

use App\Application\Api\Session\GetGroupSessions\GetGroupSessionsHandler;
use App\Application\Api\Session\GetGroupSessions\GetGroupSessionsQuery;
use App\Entity\User;
use App\Repository\GameGroupRepository;
use App\Security\Voter\GroupVoter;
use App\UI\Http\Response\JsonErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ListSessionsApiController extends AbstractController
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
        private readonly GetGroupSessionsHandler $getGroupSessionsHandler,
    ) {
    }

    #[Route('/api/groups/{groupId<\d+>}/sessions', name: 'api_group_sessions_list', methods: ['GET'])]
    public function __invoke(int $groupId): JsonResponse
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

        $sessions = $this->getGroupSessionsHandler->handle(new GetGroupSessionsQuery(
            groupId: $groupId,
            userId: (int) $user->getId(),
        ));

        $data = [];
        foreach ($sessions as $session) {
            $data[] = [
                'id' => $session->id,
                'groupId' => $session->groupId,
                'activityId' => $session->activityId,
                'activityName' => $session->activityName,
                'title' => $session->title,
                'playedAt' => $session->playedAt,
                'entriesCount' => $session->entriesCount,
            ];
        }

        return $this->json(['data' => $data]);
    }
}
