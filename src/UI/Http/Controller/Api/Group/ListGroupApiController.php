<?php

namespace App\UI\Http\Controller\Api\Group;

use App\Application\Api\Group\GetMyGroups\GetMyGroupsHandler;
use App\Application\Api\Group\GetMyGroups\GetMyGroupsQuery;
use App\Entity\User;
use App\UI\Http\Response\JsonErrorResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ListGroupApiController extends AbstractController
{
    public function __construct(
        private readonly GetMyGroupsHandler $handler,
    ) {
    }

    #[Route('/api/groups', name: 'api_group_index', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return JsonErrorResponse::create('unauthorized', 401);
        }

        $groups = $this->handler->handle(new GetMyGroupsQuery($user->getId()));

        return $this->json([
            'data' => array_map(static fn ($group) => $group->toArray(), $groups),
        ]);
    }
}
