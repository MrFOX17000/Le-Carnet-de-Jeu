<?php

namespace App\UI\Http\Controller\Api\Group;

use App\Application\Group\CreateGroup\CreateGroupCommand;
use App\Application\Group\CreateGroup\CreateGroupHandler;
use App\Entity\User;
use App\UI\Http\Response\JsonErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateGroupApiController extends AbstractController
{
    public function __construct(
        private readonly CreateGroupHandler $createGroupHandler,
    ) {
    }

    #[Route('/api/groups', name: 'api_group_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return JsonErrorResponse::create('unauthorized', 401);
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

        $result = $this->createGroupHandler->handle(new CreateGroupCommand(
            name: $name,
            creatorUserId: (int) $user->getId(),
        ));

        return $this->json([
            'data' => [
                'id' => $result->getGroupId(),
                'name' => $name,
            ],
        ], 201);
    }
}
