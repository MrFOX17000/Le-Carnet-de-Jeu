<?php

namespace App\Application\Entry\CreateMatchEntry;

use App\Domain\Entry\EntryType;
use App\Entity\Entry;
use App\Entity\EntryMatch;
use App\Application\Entry\CreateMatchEntry\CreateMatchEntryCommand;
use App\Application\Entry\CreateMatchEntry\CreateMatchEntryResult;
use App\Repository\GameGroupRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CreateMatchEntryHandler
{
    public function __construct(
        private SessionRepository $sessionRepository,
        private GameGroupRepository $gameGroupRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function handle(CreateMatchEntryCommand $command): CreateMatchEntryResult
    {
        $group = $this->gameGroupRepository->find($command->groupId);
        if (null === $group) {
            throw new \InvalidArgumentException('Group not found');
        }

        $session = $this->sessionRepository->find($command->sessionId);
        if (null === $session) {
            throw new \InvalidArgumentException('Session not found');
        }

        if ($session->getGroup()->getId() !== $group->getId()) {
            throw new \InvalidArgumentException('Session does not belong to this group');
        }

        $creator = $this->userRepository->find($command->creatorUserId);
        if (null === $creator) {
            throw new \InvalidArgumentException('Creator not found');
        }

        $homeName = trim($command->homeName);
        $awayName = trim($command->awayName);
        if ('' === $homeName || '' === $awayName) {
            throw new \InvalidArgumentException('Team names must not be empty');
        }

        if (0 === strcasecmp($homeName, $awayName)) {
            throw new \InvalidArgumentException('Home and away teams must be different');
        }

        if ($command->homeScore < 0 || $command->awayScore < 0) {
            throw new \InvalidArgumentException('Scores must be greater than or equal to 0');
        }

        $entry = new Entry(EntryType::MATCH);
        $entry->setSession($session);
        $entry->setGroup($group);
        $entry->setCreatedBy($creator);
        $entry->setLabel(null !== $command->label && '' !== trim($command->label) ? trim($command->label) : null);

        $groupMemberUserIds = [];
        foreach ($group->getGroupMembers() as $membership) {
            $memberUserId = $membership->getUser()?->getId();
            if (null !== $memberUserId) {
                $groupMemberUserIds[$memberUserId] = true;
            }
        }

        $match = new EntryMatch();
        $match->setEntry($entry);
        $match->setHomeName($homeName);
        $match->setAwayName($awayName);
        $match->setHomeScore($command->homeScore);
        $match->setAwayScore($command->awayScore);

        if (null !== $command->homeUserId) {
            $homeUser = $this->userRepository->find($command->homeUserId);
            if (null === $homeUser) {
                throw new \InvalidArgumentException('Home user not found');
            }

            if (!isset($groupMemberUserIds[$homeUser->getId()])) {
                throw new \InvalidArgumentException('Home user does not belong to group');
            }

            $match->setHomeUser($homeUser);
        }

        if (null !== $command->awayUserId) {
            $awayUser = $this->userRepository->find($command->awayUserId);
            if (null === $awayUser) {
                throw new \InvalidArgumentException('Away user not found');
            }

            if (!isset($groupMemberUserIds[$awayUser->getId()])) {
                throw new \InvalidArgumentException('Away user does not belong to group');
            }

            $match->setAwayUser($awayUser);
        }

        if (null !== $command->homeUserId && null !== $command->awayUserId && $command->homeUserId === $command->awayUserId) {
            throw new \InvalidArgumentException('Home and away users must be different');
        }

        $this->em->persist($entry);
        $this->em->flush();

        return new CreateMatchEntryResult(
            entryId: $entry->getId(),
            sessionId: $session->getId(),
        );
    }
}
