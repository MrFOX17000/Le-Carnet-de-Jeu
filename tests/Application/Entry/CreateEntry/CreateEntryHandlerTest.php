<?php

namespace App\Tests\Application\Entry\CreateEntry;

use App\Application\Entry\CreateEntry\CreateEntryCommand;
use App\Application\Entry\CreateEntry\CreateEntryHandler;
use App\Domain\Entry\EntryType;
use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\Entry;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class CreateEntryHandlerTest extends DbWebTestCase
{
    private CreateEntryHandler $handler;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->handler = static::getContainer()->get(CreateEntryHandler::class);
    }

    public function testOwnerCanCreateEntry(): void
    {
        // Create user
        $user = new User();
        $user->setEmail('creator@test.com');
        $user->setPassword('hashed');
        $user->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($user);
        $this->em->flush();

        // Create group
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($user);
        $this->em->persist($group);
        $this->em->flush();

        // Create activity
        $activity = new Activity();
        $activity->setName('Test Activity');
        $activity->setGroup($group);
        $activity->setCreatedBy($user);
        $this->em->persist($activity);
        $this->em->flush();

        // Create session
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setPlayedAt(new \DateTimeImmutable());
        $session->setCreatedBy($user);
        $this->em->persist($session);
        $this->em->flush();

        $command = new CreateEntryCommand(
            sessionId: $session->getId(),
            groupId: $group->getId(),
            creatorUserId: $user->getId(),
            type: EntryType::SCORE_SIMPLE,
            label: 'Manche 1',
            scores: [
                ['participantName' => 'Alice', 'score' => 10.0],
                ['participantName' => 'Bob', 'score' => 8.0],
            ],
        );

        $result = $this->handler->handle($command);

        self::assertNotNull($result);
        self::assertIsInt($result->entryId);
        self::assertEquals($session->getId(), $result->sessionId);

        $this->em->clear();
        $entry = $this->em->getRepository(Entry::class)->find($result->entryId);
        self::assertNotNull($entry);
        self::assertEquals('Manche 1', $entry->getLabel());
        self::assertCount(2, $entry->getScores());
    }

    public function testEntryIsAttachedToCorrectGroup(): void
    {
        // Create user
        $user = new User();
        $user->setEmail('owner@test.com');
        $user->setPassword('hashed');
        $user->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($user);
        $this->em->flush();

        // Create group
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($user);
        $this->em->persist($group);
        $this->em->flush();

        // Create activity
        $activity = new Activity();
        $activity->setName('Test Activity');
        $activity->setGroup($group);
        $activity->setCreatedBy($user);
        $this->em->persist($activity);
        $this->em->flush();

        // Create session
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setPlayedAt(new \DateTimeImmutable());
        $session->setCreatedBy($user);
        $this->em->persist($session);
        $this->em->flush();

        $command = new CreateEntryCommand(
            sessionId: $session->getId(),
            groupId: $group->getId(),
            creatorUserId: $user->getId(),
            type: EntryType::SCORE_SIMPLE,
            label: null,
            scores: [
                ['participantName' => 'Alice', 'score' => 10.0],
            ],
        );

        $result = $this->handler->handle($command);
        $this->em->clear();
        $entry = $this->em->getRepository(Entry::class)->find($result->entryId);

        self::assertEquals($group->getId(), $entry->getGroup()->getId());
        self::assertEquals($session->getId(), $entry->getSession()->getId());
    }

    public function testSessionFromAnotherGroupThrowsException(): void
    {
        // Create user
        $user = new User();
        $user->setEmail('test@test.com');
        $user->setPassword('hashed');
        $user->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($user);
        $this->em->flush();

        // Create first group with session1
        $group1 = new GameGroup();
        $group1->setName('First Group');
        $group1->setCreatedAt(new \DateTimeImmutable());
        $group1->setCreatedBy($user);
        $this->em->persist($group1);
        $this->em->flush();

        $activity1 = new Activity();
        $activity1->setName('Activity Group 1');
        $activity1->setGroup($group1);
        $activity1->setCreatedBy($user);
        $this->em->persist($activity1);
        $this->em->flush();

        $session1 = new Session();
        $session1->setActivity($activity1);
        $session1->setGroup($group1);
        $session1->setPlayedAt(new \DateTimeImmutable());
        $session1->setCreatedBy($user);
        $this->em->persist($session1);
        $this->em->flush();

        // Create second group with session2
        $group2 = new GameGroup();
        $group2->setName('Second Group');
        $group2->setCreatedAt(new \DateTimeImmutable());
        $group2->setCreatedBy($user);
        $this->em->persist($group2);
        $this->em->flush();

        $activity2 = new Activity();
        $activity2->setName('Activity Group 2');
        $activity2->setGroup($group2);
        $activity2->setCreatedBy($user);
        $this->em->persist($activity2);
        $this->em->flush();

        $session2 = new Session();
        $session2->setActivity($activity2);
        $session2->setGroup($group2);
        $session2->setPlayedAt(new \DateTimeImmutable());
        $session2->setCreatedBy($user);
        $this->em->persist($session2);
        $this->em->flush();

        // Try to create entry in group1 but with session from group2
        $command = new CreateEntryCommand(
            sessionId: $session2->getId(),
            groupId: $group1->getId(),
            creatorUserId: $user->getId(),
            type: EntryType::SCORE_SIMPLE,
            label: null,
            scores: [
                ['participantName' => 'Alice', 'score' => 10.0],
            ],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Session does not belong to this group');
        $this->handler->handle($command);
    }

    public function testEntryWithoutScoresThrowsException(): void
    {
        // Create user
        $user = new User();
        $user->setEmail('owner2@test.com');
        $user->setPassword('hashed');
        $user->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($user);
        $this->em->flush();

        // Create group
        $group = new GameGroup();
        $group->setName('Test Group 2');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($user);
        $this->em->persist($group);
        $this->em->flush();

        // Create activity
        $activity = new Activity();
        $activity->setName('Test Activity 2');
        $activity->setGroup($group);
        $activity->setCreatedBy($user);
        $this->em->persist($activity);
        $this->em->flush();

        // Create session
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setPlayedAt(new \DateTimeImmutable());
        $session->setCreatedBy($user);
        $this->em->persist($session);
        $this->em->flush();

        $command = new CreateEntryCommand(
            sessionId: $session->getId(),
            groupId: $group->getId(),
            creatorUserId: $user->getId(),
            type: EntryType::SCORE_SIMPLE,
            label: null,
            scores: [],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entry must have at least one score');
        $this->handler->handle($command);
    }

    public function testCanLinkParticipantUserOnScoreEntry(): void
    {
        $owner = new User();
        $owner->setEmail('owner-link@test.com');
        $owner->setPassword('hashed');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $participant = new User();
        $participant->setEmail('participant-link@test.com');
        $participant->setPassword('hashed');
        $participant->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($owner);
        $this->em->persist($participant);
        $this->em->flush();

        $group = new GameGroup();
        $group->setName('Linked Participant Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);
        $this->em->persist($group);

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $ownerMembership->setUser($owner);
        $group->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $memberMembership->setUser($participant);
        $group->addGroupMember($memberMembership);

        $this->em->persist($ownerMembership);
        $this->em->persist($memberMembership);

        $activity = new Activity();
        $activity->setName('Linked Activity');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $this->em->persist($activity);

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setPlayedAt(new \DateTimeImmutable());
        $session->setCreatedBy($owner);
        $this->em->persist($session);
        $this->em->flush();

        $result = $this->handler->handle(new CreateEntryCommand(
            sessionId: $session->getId(),
            groupId: $group->getId(),
            creatorUserId: $owner->getId(),
            type: EntryType::SCORE_SIMPLE,
            label: 'With linked user',
            scores: [
                ['participantName' => 'Participant', 'score' => 20.0, 'userId' => $participant->getId()],
            ],
        ));

        $this->em->clear();
        $entry = $this->em->getRepository(Entry::class)->find($result->entryId);

        self::assertNotNull($entry);
        self::assertCount(1, $entry->getScores());
        $score = $entry->getScores()->first();
        self::assertNotFalse($score);
        self::assertSame($participant->getId(), $score->getUser()?->getId());
    }
}
