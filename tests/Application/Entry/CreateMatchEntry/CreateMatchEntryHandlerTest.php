<?php

namespace App\Tests\Application\Entry\CreateMatchEntry;

use App\Application\Entry\CreateMatchEntry\CreateMatchEntryCommand;
use App\Application\Entry\CreateMatchEntry\CreateMatchEntryHandler;
use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\Entry;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class CreateMatchEntryHandlerTest extends DbWebTestCase
{
    private CreateMatchEntryHandler $handler;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->handler = static::getContainer()->get(CreateMatchEntryHandler::class);
    }

    public function testOwnerCanCreateMatchEntry(): void
    {
        [$user, $group, $session] = $this->createUserGroupSession('owner-match@test.com', 'Group Match');

        $result = $this->handler->handle(new CreateMatchEntryCommand(
            groupId: $group->getId(),
            sessionId: $session->getId(),
            creatorUserId: $user->getId(),
            homeName: 'Tigers',
            awayName: 'Wolves',
            homeScore: 3,
            awayScore: 1,
            label: 'Demi-finale',
        ));

        self::assertIsInt($result->entryId);
        self::assertSame($session->getId(), $result->sessionId);

        $this->em->clear();
        $entry = $this->em->getRepository(Entry::class)->find($result->entryId);

        self::assertNotNull($entry);
        self::assertSame('match', $entry->getType()->value);
        self::assertNotNull($entry->getEntryMatch());
        self::assertSame('Tigers', $entry->getEntryMatch()->getHomeName());
        self::assertSame('Wolves', $entry->getEntryMatch()->getAwayName());
        self::assertSame(3, $entry->getEntryMatch()->getHomeScore());
        self::assertSame(1, $entry->getEntryMatch()->getAwayScore());
    }

    public function testSessionFromAnotherGroupThrowsException(): void
    {
        [$user, $group1] = $this->createUserGroupSession('owner-a@test.com', 'Group A');
        [, $group2, $session2] = $this->createUserGroupSession('owner-b@test.com', 'Group B');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Session does not belong to this group');

        $this->handler->handle(new CreateMatchEntryCommand(
            groupId: $group1->getId(),
            sessionId: $session2->getId(),
            creatorUserId: $user->getId(),
            homeName: 'A',
            awayName: 'B',
            homeScore: 1,
            awayScore: 0,
        ));
    }

    public function testHomeAndAwayTeamsMustBeDifferent(): void
    {
        [$user, $group, $session] = $this->createUserGroupSession('owner-c@test.com', 'Group C');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Home and away teams must be different');

        $this->handler->handle(new CreateMatchEntryCommand(
            groupId: $group->getId(),
            sessionId: $session->getId(),
            creatorUserId: $user->getId(),
            homeName: 'Lions',
            awayName: 'lions',
            homeScore: 2,
            awayScore: 1,
        ));
    }

    public function testNegativeScoresThrowException(): void
    {
        [$user, $group, $session] = $this->createUserGroupSession('owner-d@test.com', 'Group D');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scores must be greater than or equal to 0');

        $this->handler->handle(new CreateMatchEntryCommand(
            groupId: $group->getId(),
            sessionId: $session->getId(),
            creatorUserId: $user->getId(),
            homeName: 'Sharks',
            awayName: 'Eagles',
            homeScore: -1,
            awayScore: 2,
        ));
    }

    public function testEmptyTeamNameThrowsException(): void
    {
        [$user, $group, $session] = $this->createUserGroupSession('owner-e@test.com', 'Group E');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Team names must not be empty');

        $this->handler->handle(new CreateMatchEntryCommand(
            groupId: $group->getId(),
            sessionId: $session->getId(),
            creatorUserId: $user->getId(),
            homeName: '   ',
            awayName: 'Team B',
            homeScore: 0,
            awayScore: 0,
        ));
    }

    /**
     * @return array{0: User, 1: GameGroup, 2: Session}
     */
    private function createUserGroupSession(string $email, string $groupName): array
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('hashed');
        $user->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($user);
        $this->em->flush();

        $group = new GameGroup();
        $group->setName($groupName);
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($user);
        $this->em->persist($group);
        $this->em->flush();

        $activity = new Activity();
        $activity->setName('Activity '.$groupName);
        $activity->setGroup($group);
        $activity->setCreatedBy($user);
        $this->em->persist($activity);
        $this->em->flush();

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setPlayedAt(new \DateTimeImmutable());
        $session->setCreatedBy($user);
        $this->em->persist($session);
        $this->em->flush();

        return [$user, $group, $session];
    }

    public function testCanLinkHomeAndAwayUsers(): void
    {
        $owner = new User();
        $owner->setEmail('owner-linked-match@test.com');
        $owner->setPassword('hashed');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $homeUser = new User();
        $homeUser->setEmail('home-linked@test.com');
        $homeUser->setPassword('hashed');
        $homeUser->setCreatedAt(new \DateTimeImmutable());

        $awayUser = new User();
        $awayUser->setEmail('away-linked@test.com');
        $awayUser->setPassword('hashed');
        $awayUser->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($owner);
        $this->em->persist($homeUser);
        $this->em->persist($awayUser);
        $this->em->flush();

        $group = new GameGroup();
        $group->setName('Linked Match Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);
        $this->em->persist($group);

        foreach ([
            [$owner, GroupRole::OWNER],
            [$homeUser, GroupRole::MEMBER],
            [$awayUser, GroupRole::MEMBER],
        ] as [$memberUser, $role]) {
            $membership = new GroupMember($role);
            $membership->setUser($memberUser);
            $group->addGroupMember($membership);
            $this->em->persist($membership);
        }

        $activity = new Activity();
        $activity->setName('Linked Match Activity');
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

        $result = $this->handler->handle(new CreateMatchEntryCommand(
            groupId: $group->getId(),
            sessionId: $session->getId(),
            creatorUserId: $owner->getId(),
            homeName: 'Home Team',
            awayName: 'Away Team',
            homeScore: 2,
            awayScore: 1,
            label: 'Linked match',
            homeUserId: $homeUser->getId(),
            awayUserId: $awayUser->getId(),
        ));

        $this->em->clear();
        $entry = $this->em->getRepository(Entry::class)->find($result->entryId);

        self::assertNotNull($entry);
        self::assertNotNull($entry->getEntryMatch());
        self::assertSame($homeUser->getId(), $entry->getEntryMatch()?->getHomeUser()?->getId());
        self::assertSame($awayUser->getId(), $entry->getEntryMatch()?->getAwayUser()?->getId());
    }
}
