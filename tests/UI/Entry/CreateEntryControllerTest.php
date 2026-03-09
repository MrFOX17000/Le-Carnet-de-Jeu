<?php

namespace App\Tests\UI\Entry;

use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Domain\Group\GroupRole;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class CreateEntryControllerTest extends DbWebTestCase
{
    public function testOwnerCanAccessCreateEntryForm(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Create owner user
        $owner = new User();
        $owner->setEmail('owner@test.com');
        $owner->setPassword('hashed');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);

        // Create group
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());
        $em->persist($group);

        // Add owner as GROUP member  
        $groupMember = new GroupMember(GroupRole::OWNER);
        $groupMember->setGroup($group);
        $groupMember->setUser($owner);
        $group->addGroupMember($groupMember);
        $owner->addGroupMember($groupMember);
        $em->persist($groupMember);

        // Create activity
        $activity = new Activity();
        $activity->setName('Test Activity');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $em->persist($activity);

        // Create session
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setPlayedAt(new \DateTimeImmutable());
        $session->setCreatedBy($owner);
        $em->persist($session);

        $em->flush();

        $client->loginUser($owner);

        $url = "/groups/{$group->getId()}/sessions/{$session->getId()}/entries/create";
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="label"]');
        self::assertSelectorExists('input[name="participant_names[]"]');
        self::assertSelectorExists('input[name="scores[]"]');
    }

    public function testNonMemberCannotAccessCreateEntryForm(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Create users
        $nonMember = new User();
        $nonMember->setEmail('nonmember@test.com');
        $nonMember->setPassword('hashed');
        $nonMember->setCreatedAt(new \DateTimeImmutable());
        $em->persist($nonMember);

        $owner = new User();
        $owner->setEmail('owner2@test.com');
        $owner->setPassword('hashed');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);
        $em->flush();

        // Create group
        $group = new GameGroup();
        $group->setName('Test Group 2');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());
        $em->persist($group);

        // Add owner as GROUP member (not the nonMember)
        $groupMember = new GroupMember(GroupRole::OWNER);
        $groupMember->setGroup($group);
        $groupMember->setUser($owner);
        $group->addGroupMember($groupMember);
        $owner->addGroupMember($groupMember);
        $em->persist($groupMember);

        // Create activity
        $activity = new Activity();
        $activity->setName('Test Activity 2');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $em->persist($activity);

        // Create session
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setPlayedAt(new \DateTimeImmutable());
        $session->setCreatedBy($owner);
        $em->persist($session);

        $em->flush();

        $client->loginUser($nonMember);

        $url = "/groups/{$group->getId()}/sessions/{$session->getId()}/entries/create";
        $client->request('GET', $url);

        self::assertResponseStatusCodeSame(403);
    }
}
