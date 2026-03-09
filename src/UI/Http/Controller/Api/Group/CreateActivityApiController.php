<?php

namespace App\UI\Http\Controller\Api\Group;

use App\Application\Activity\CreateActivity\CreateActivityCommand;
use App\Application\Activity\CreateActivity\CreateActivityHandler;
use App\Entity\User;
use App\Repository\GameGroupRepository;
use App\Security\Voter\GroupVoter;
use App\UI\Http\Response\JsonErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateActivityApiController extends AbstractController
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
        private readonly CreateActivityHandler $createActivityHandler,
    ) {
    }

    #[Route('/api/groups/{groupId<\d+>}/activities', name: 'api_group_activity_create', methods: ['POST'])]
    public function __invoke(int $groupId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return JsonErrorResponse::create('unauthorized', 401);
        }

        $group = $this->groupRepository->find($groupId);
        if ($group === null) {
            return JsonErrorResponse::create('not_found', 404);
        }

        if (!$this->isGranted(GroupVoter::MANAGE, $group)) {
            return JsonErrorResponse::create('forbidden', 403);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return JsonErrorResponse::create('invalid_json', 400);
        }

        if (!is_array($payload)) {
            return JsonErrorResponse::create('invalid_json', 400);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return JsonErrorResponse::create('name_required', 422);
        }

        $result = $this->createActivityHandler->handle(new CreateActivityCommand(
            groupId: $groupId,
            name: $name,
            creatorUserId: (int) $user->getId(),
        ));

        return $this->json([
            'data' => [
                'id' => $result->getActivityId(),
                'groupId' => $result->getGroupId(),
                'name' => $name,
            ],
        ], 201);
    }
}
