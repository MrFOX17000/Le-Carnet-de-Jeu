<?php

namespace App\UI\Http\Controller\Api\Group;

use App\Application\Session\CreateSession\CreateSessionCommand;
use App\Application\Session\CreateSession\CreateSessionHandler;
use App\Entity\User;
use App\Repository\GameGroupRepository;
use App\Security\Voter\GroupVoter;
use App\UI\Http\Response\JsonErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateSessionApiController extends AbstractController
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
        private readonly CreateSessionHandler $createSessionHandler,
    ) {
    }

    #[Route('/api/groups/{groupId<\d+>}/sessions', name: 'api_group_session_create', methods: ['POST'])]
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

        // Valider activityId
        if (!isset($payload['activityId'])) {
            return JsonErrorResponse::create('activity_id_required', 422);
        }

        $activityId = (int) $payload['activityId'];

        // Valider playedAt
        if (!isset($payload['playedAt'])) {
            return JsonErrorResponse::create('played_at_required', 422);
        }

        try {
            $playedAt = new \DateTimeImmutable($payload['playedAt']);
        } catch (\Exception) {
            return JsonErrorResponse::create('invalid_played_at', 400);
        }

        $title = isset($payload['title']) ? trim((string) $payload['title']) : null;
        if ($title === '') {
            $title = null;
        }

        try {
            $result = $this->createSessionHandler->handle(new CreateSessionCommand(
                groupId: $groupId,
                activityId: $activityId,
                creatorUserId: (int) $user->getId(),
                playedAt: $playedAt,
                title: $title,
            ));
        } catch (\InvalidArgumentException $e) {
            // Vérifier si c'est relatif à l'activité qui n'appartient pas au groupe
            if (str_contains($e->getMessage(), 'does not belong to group')) {
                return JsonErrorResponse::create('activity_not_in_group', 422);
            }
            // Autres cas InvalidArgumentException (activity not found, etc.)
            return JsonErrorResponse::create('invalid_request', 422);
        } catch (\LogicException) {
            return JsonErrorResponse::create('internal_error', 500);
        }

        return $this->json([
            'data' => [
                'id' => $result->getSessionId(),
                'groupId' => $result->getGroupId(),
            ],
        ], 201);
    }
}
