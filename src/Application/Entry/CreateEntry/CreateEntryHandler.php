<?php

namespace App\Application\Entry\CreateEntry;

use App\Entity\Entry;
use App\Entity\EntryScore;
use App\Repository\GameGroupRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CreateEntryHandler
{
    public function __construct(
        private SessionRepository $sessionRepository,
        private GameGroupRepository $gameGroupRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function handle(\App\Application\Entry\CreateEntry\CreateEntryCommand $command): \App\Application\Entry\CreateEntry\CreateEntryResult
    {
        // Verify group exists
        $group = $this->gameGroupRepository->find($command->groupId);
        if (null === $group) {
            throw new \InvalidArgumentException('Group not found');
        }

        // Verify session exists
        $session = $this->sessionRepository->find($command->sessionId);
        if (null === $session) {
            throw new \InvalidArgumentException('Session not found');
        }

        // Critical: verify session belongs to the correct group (prevent cross-group forgery)
        if ($session->getGroup()->getId() !== $group->getId()) {
            throw new \InvalidArgumentException('Session does not belong to this group');
        }

        // Verify creator exists
        $creator = $this->userRepository->find($command->creatorUserId);
        if (null === $creator) {
            throw new \InvalidArgumentException('Creator not found');
        }

        // Verify at least one score exists
        if (empty($command->scores)) {
            throw new \InvalidArgumentException('Entry must have at least one score');
        }

        // Create entry
        $entry = new Entry($command->type);
        $entry->setSession($session);
        $entry->setGroup($group);
        $entry->setLabel($command->label);
        $entry->setCreatedBy($creator);

        $groupMemberUserIds = [];
        foreach ($group->getGroupMembers() as $membership) {
            $memberUserId = $membership->getUser()?->getId();
            if (null !== $memberUserId) {
                $groupMemberUserIds[$memberUserId] = true;
            }
        }

        // Add scores
        foreach ($command->scores as $scoreData) {
            $entryScore = new EntryScore();
            $entryScore->setParticipantName($scoreData['participantName']);
            $entryScore->setScore($scoreData['score']);

            $participantUserId = $scoreData['userId'] ?? null;
            if (null !== $participantUserId) {
                $participantUser = $this->userRepository->find((int) $participantUserId);
                if (null === $participantUser) {
                    throw new \InvalidArgumentException('Participant user not found');
                }

                if (!isset($groupMemberUserIds[$participantUser->getId()])) {
                    throw new \InvalidArgumentException('Participant user does not belong to group');
                }

                $entryScore->setUser($participantUser);
            }

            $entry->addScore($entryScore);
        }

        // Persist
        $this->em->persist($entry);
        $this->em->flush();

        return new \App\Application\Entry\CreateEntry\CreateEntryResult(
            entryId: $entry->getId(),
            sessionId: $session->getId(),
        );
    }
}
