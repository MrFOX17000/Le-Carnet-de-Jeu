<?php

namespace App\UI\Http\Controller\Api\Group;

use App\Application\Api\Group\GetGroupDetails\GetGroupDetailsHandler;
use App\Application\Api\Group\GetGroupDetails\GetGroupDetailsQuery;
use App\Entity\User;
use App\Repository\GameGroupRepository;
use App\Security\Voter\GroupVoter;
use App\UI\Http\Response\JsonErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ShowGroupApiController extends AbstractController
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
        private readonly GetGroupDetailsHandler $handler,
    ) {
    }

    #[Route('/api/groups/{id<\d+>}', name: 'api_group_show', methods: ['GET'])]
    public function __invoke(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return JsonErrorResponse::create('unauthorized', 401);
        }

        $group = $this->groupRepository->find($id);
        if ($group === null) {
            return JsonErrorResponse::create('not_found', 404);
        }

        if (!$this->isGranted(GroupVoter::VIEW, $group)) {
            return JsonErrorResponse::create('forbidden', 403);
        }

        $data = $this->handler->handle(new GetGroupDetailsQuery(
            groupId: $id,
            userId: $user->getId(),
        ));

        if ($data === null) {
            return JsonErrorResponse::create('not_found', 404);
        }

        return $this->json(['data' => $data->toArray()]);
    }
}
