<?php

namespace App\Application\Api\Group\GetGroupDetails;

use App\Application\Api\Group\Dto\GroupOutput;
use App\Entity\GameGroup;
use App\Repository\GameGroupRepository;

final class GetGroupDetailsHandler
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
    ) {
    }

    public function handle(GetGroupDetailsQuery $query): ?GroupOutput
    {
        $group = $this->groupRepository->find($query->groupId);
        if (!$group instanceof GameGroup) {
            return null;
        }

        $role = null;
        foreach ($group->getGroupMembers() as $member) {
            if ($member->getUser()?->getId() !== $query->userId) {
                continue;
            }

            $role = $member->getRole()->value;
            break;
        }

        return new GroupOutput(
            id: (int) $group->getId(),
            name: (string) $group->getName(),
            createdAt: $group->getCreatedAt()->format(DATE_ATOM),
            role: $role,
        );
    }
}
