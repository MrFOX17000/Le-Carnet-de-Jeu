<?php

namespace App\Application\Invite\CreateInvite;

use App\Entity\Invite;
use App\Repository\GameGroupRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CreateInviteHandler
{
    public function __construct(
        private readonly GameGroupRepository $groupRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function handle(CreateInviteCommand $command): CreateInviteResult
    {
        $group = $this->groupRepository->find($command->getGroupId());
        if ($group === null) {
            throw new \InvalidArgumentException(
                sprintf('Group with ID %d not found.', $command->getGroupId())
            );
        }

        $creator = $this->userRepository->find($command->getCreatorUserId());
        if ($creator === null) {
            throw new \InvalidArgumentException(
                sprintf('User with ID %d not found.', $command->getCreatorUserId())
            );
        }

        $email = trim(mb_strtolower($command->getEmail()));
        if ($email === '') {
            throw new \InvalidArgumentException('Invite email cannot be empty.');
        }

        $invite = new Invite($command->getRole());
        $invite->setEmail($email);

        $token = bin2hex(random_bytes(32));
        $invite->setToken($token);

        $invite->setExpiresAt(new \DateTimeImmutable('+7 days'));

        // Synchronisation des relations
        $group->addInvite($invite);
        $creator->addInvite($invite);

        $this->entityManager->persist($invite);
        $this->entityManager->flush();

        $inviteId = $invite->getId();
        if ($inviteId === null) {
            throw new \LogicException('Invite id should not be null after flush.');
        }

        return new CreateInviteResult($inviteId, $token);
    }
}