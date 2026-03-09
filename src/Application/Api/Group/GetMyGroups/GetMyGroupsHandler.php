<?php

namespace App\Application\Api\Group\GetMyGroups;

use App\Application\Api\Group\Dto\GroupOutput;
use App\Entity\GameGroup;
use App\Repository\GameGroupRepository;

final class GetMyGroupsHandler
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
    ) {
    }

    /**
     * @return list<GroupOutput>
     */
    public function handle(GetMyGroupsQuery $query): array
    {
        $groups = $this->groupRepository->findGroupsForUser($query->userId);

        $result = [];
        foreach ($groups as $group) {
            $result[] = $this->mapGroup($group, $query->userId);
        }

        return $result;
    }

    private function mapGroup(GameGroup $group, int $userId): GroupOutput
    {
        $role = null;
        foreach ($group->getGroupMembers() as $member) {
            if ($member->getUser()?->getId() !== $userId) {
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
