<?php

namespace App\UI\Http\Controller\Api\Group;

use App\Application\Entry\CreateEntry\CreateEntryCommand;
use App\Application\Entry\CreateEntry\CreateEntryHandler;
use App\Domain\Entry\EntryType;
use App\Entity\User;
use App\Repository\GameGroupRepository;
use App\Repository\SessionRepository;
use App\Security\Voter\GroupVoter;
use App\UI\Http\Response\JsonErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateScoreSimpleEntryApiController extends AbstractController
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly CreateEntryHandler $createEntryHandler,
    ) {
    }

    #[Route('/api/groups/{groupId<\d+>}/sessions/{sessionId<\d+>}/entries/score-simple', name: 'api_group_session_entry_score_simple_create', methods: ['POST'])]
    public function __invoke(int $groupId, int $sessionId, Request $request): JsonResponse
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

        $session = $this->sessionRepository->find($sessionId);
        if ($session === null) {
            return JsonErrorResponse::create('not_found', 404);
        }

        if ($session->getGroup()->getId() !== $group->getId()) {
            return JsonErrorResponse::create('session_not_in_group', 422);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return JsonErrorResponse::create('invalid_json', 400);
        }

        if (!is_array($payload)) {
            return JsonErrorResponse::create('invalid_json', 400);
        }

        // Valider scores
        if (!isset($payload['scores'])) {
            return JsonErrorResponse::create('scores_required', 422);
        }

        if (!is_array($payload['scores'])) {
            return JsonErrorResponse::create('scores_must_be_array', 422);
        }

        if (empty($payload['scores'])) {
            return JsonErrorResponse::create('scores_empty', 422);
        }

        // Valider chaque score
        $scores = [];
        foreach ($payload['scores'] as $index => $scoreData) {
            if (!is_array($scoreData)) {
                return JsonErrorResponse::create('score_must_be_object', 422);
            }

            if (!isset($scoreData['participantName']) || is_string($scoreData['participantName']) === false) {
                return JsonErrorResponse::create('participant_name_required', 422);
            }

            if (!isset($scoreData['score'])) {
                return JsonErrorResponse::create('score_value_required', 422);
            }

            $score = $scoreData['score'];
            if (is_numeric($score) === false) {
                return JsonErrorResponse::create('score_must_be_numeric', 422);
            }

            $participantUserId = null;
            if (array_key_exists('userId', $scoreData) && null !== $scoreData['userId']) {
                if (is_numeric($scoreData['userId']) === false) {
                    return JsonErrorResponse::create('participant_user_id_invalid', 422);
                }

                $participantUserId = (int) $scoreData['userId'];
                if ($participantUserId <= 0) {
                    return JsonErrorResponse::create('participant_user_id_invalid', 422);
                }
            }

            $scores[] = [
                'participantName' => trim((string) $scoreData['participantName']),
                'score' => (float) $score,
                'userId' => $participantUserId,
            ];
        }

        $label = isset($payload['label']) ? trim((string) $payload['label']) : null;
        if ($label === '') {
            $label = null;
        }

        try {
            $result = $this->createEntryHandler->handle(new CreateEntryCommand(
                sessionId: $sessionId,
                groupId: $groupId,
                creatorUserId: (int) $user->getId(),
                type: EntryType::SCORE_SIMPLE,
                label: $label,
                scores: $scores,
            ));
        } catch (\InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'Participant user not found')) {
                return JsonErrorResponse::create('participant_user_not_found', 422);
            }

            if (str_contains($e->getMessage(), 'Participant user does not belong to group')) {
                return JsonErrorResponse::create('participant_user_not_in_group', 422);
            }

            return JsonErrorResponse::create('invalid_request', 422);
        } catch (\LogicException) {
            return JsonErrorResponse::create('internal_error', 500);
        }

        return $this->json([
            'data' => [
                'id' => $result->entryId,
                'sessionId' => $result->sessionId,
                'groupId' => $groupId,
                'type' => 'score_simple',
            ],
        ], 201);
    }
}
