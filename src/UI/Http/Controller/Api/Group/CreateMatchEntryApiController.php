<?php

namespace App\UI\Http\Controller\Api\Group;

use App\Application\Entry\CreateMatchEntry\CreateMatchEntryCommand;
use App\Application\Entry\CreateMatchEntry\CreateMatchEntryHandler;
use App\Entity\User;
use App\Repository\GameGroupRepository;
use App\Repository\SessionRepository;
use App\Security\Voter\GroupVoter;
use App\UI\Http\Response\JsonErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateMatchEntryApiController extends AbstractController
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly CreateMatchEntryHandler $createMatchEntryHandler,
    ) {
    }

    #[Route('/api/groups/{groupId<\d+>}/sessions/{sessionId<\d+>}/entries/match', name: 'api_group_session_entry_match_create', methods: ['POST'])]
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

        // Valider homeName
        if (!isset($payload['homeName'])) {
            return JsonErrorResponse::create('home_name_required', 422);
        }

        $homeName = trim((string) $payload['homeName']);
        if ($homeName === '') {
            return JsonErrorResponse::create('home_name_required', 422);
        }

        // Valider awayName
        if (!isset($payload['awayName'])) {
            return JsonErrorResponse::create('away_name_required', 422);
        }

        $awayName = trim((string) $payload['awayName']);
        if ($awayName === '') {
            return JsonErrorResponse::create('away_name_required', 422);
        }

        // Valider que les équipes sont différentes (case-insensitive)
        if (strcasecmp($homeName, $awayName) === 0) {
            return JsonErrorResponse::create('teams_must_be_different', 422);
        }

        // Valider homeScore
        if (!isset($payload['homeScore'])) {
            return JsonErrorResponse::create('home_score_required', 422);
        }

        if (!is_numeric($payload['homeScore'])) {
            return JsonErrorResponse::create('home_score_must_be_numeric', 422);
        }

        $homeScore = (int) $payload['homeScore'];
        if ($homeScore < 0) {
            return JsonErrorResponse::create('home_score_must_be_positive', 422);
        }

        // Valider awayScore
        if (!isset($payload['awayScore'])) {
            return JsonErrorResponse::create('away_score_required', 422);
        }

        if (!is_numeric($payload['awayScore'])) {
            return JsonErrorResponse::create('away_score_must_be_numeric', 422);
        }

        $awayScore = (int) $payload['awayScore'];
        if ($awayScore < 0) {
            return JsonErrorResponse::create('away_score_must_be_positive', 422);
        }

        $label = isset($payload['label']) ? trim((string) $payload['label']) : null;
        if ($label === '') {
            $label = null;
        }

        $homeUserId = null;
        if (array_key_exists('homeUserId', $payload) && null !== $payload['homeUserId']) {
            if (is_numeric($payload['homeUserId']) === false || (int) $payload['homeUserId'] <= 0) {
                return JsonErrorResponse::create('home_user_id_invalid', 422);
            }
            $homeUserId = (int) $payload['homeUserId'];
        }

        $awayUserId = null;
        if (array_key_exists('awayUserId', $payload) && null !== $payload['awayUserId']) {
            if (is_numeric($payload['awayUserId']) === false || (int) $payload['awayUserId'] <= 0) {
                return JsonErrorResponse::create('away_user_id_invalid', 422);
            }
            $awayUserId = (int) $payload['awayUserId'];
        }

        try {
            $result = $this->createMatchEntryHandler->handle(new CreateMatchEntryCommand(
                groupId: $groupId,
                sessionId: $sessionId,
                creatorUserId: (int) $user->getId(),
                homeName: $homeName,
                awayName: $awayName,
                homeScore: $homeScore,
                awayScore: $awayScore,
                label: $label,
                homeUserId: $homeUserId,
                awayUserId: $awayUserId,
            ));
        } catch (\InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'Home user not found')) {
                return JsonErrorResponse::create('home_user_not_found', 422);
            }

            if (str_contains($e->getMessage(), 'Away user not found')) {
                return JsonErrorResponse::create('away_user_not_found', 422);
            }

            if (str_contains($e->getMessage(), 'Home user does not belong to group')) {
                return JsonErrorResponse::create('home_user_not_in_group', 422);
            }

            if (str_contains($e->getMessage(), 'Away user does not belong to group')) {
                return JsonErrorResponse::create('away_user_not_in_group', 422);
            }

            if (str_contains($e->getMessage(), 'Home and away users must be different')) {
                return JsonErrorResponse::create('match_users_must_be_different', 422);
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
                'type' => 'match',
            ],
        ], 201);
    }
}
