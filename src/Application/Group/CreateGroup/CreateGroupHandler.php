<?php

namespace App\Application\Group\CreateGroup;

use App\Domain\Group\GroupRole;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CreateGroupHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function handle(CreateGroupCommand $command): CreateGroupResult
    {
        $user = $this->userRepository->find($command->getCreatorUserId());

        if ($user === null) {
            throw new \InvalidArgumentException(
                sprintf('User with ID %d not found', $command->getCreatorUserId())
            );
        }

        $gameGroup = new GameGroup();
        $gameGroup->setName($command->getName());
        $gameGroup->setCreatedAt(new \DateTimeImmutable());
        $gameGroup->setCreatedBy($user);

        $groupMember = new GroupMember(GroupRole::OWNER);

        // Synchronise les 2 côtés des relations
        $gameGroup->addGroupMember($groupMember);
        $user->addGroupMember($groupMember);

        $this->entityManager->persist($gameGroup);
        $this->entityManager->persist($groupMember);
        $this->entityManager->flush();

        $groupId = $gameGroup->getId();

        if ($groupId === null) {
            throw new \LogicException('GameGroup id should not be null after flush.');
        }

        return new CreateGroupResult($groupId);
    }
}