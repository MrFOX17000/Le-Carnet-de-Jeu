<?php

namespace App\Tests\Security;

use App\Domain\Group\GroupRole;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class GroupVoterTest extends DbWebTestCase
{
    public function testMemberCanViewGroup(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('groupvoter-member-1@test.local');
        $user->setPassword('dummy');

        $group = new GameGroup();
        $group->setName('Groupe Alpha');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($user);

        $membership = new GroupMember(GroupRole::OWNER);

        // Synchronise les 2 côtés des relations
        $group->addGroupMember($membership);
        $user->addGroupMember($membership);

        $em->persist($user);
        $em->persist($group);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/groups/' . $group->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Groupe Alpha', $client->getResponse()->getContent());
    }

    public function testNonMemberCannotViewGroup(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('groupvoter-owner-1@test.local');
        $owner->setPassword('dummy');

        $intruder = new User();
        $intruder->setEmail('groupvoter-intruder-1@test.local');
        $intruder->setPassword('dummy');

        $group = new GameGroup();
        $group->setName('Groupe Secret');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $membership = new GroupMember(GroupRole::OWNER);

        // Synchronise les 2 côtés des relations
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);

        $em->persist($owner);
        $em->persist($intruder);
        $em->persist($group);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($intruder);
        $client->request('GET', '/groups/' . $group->getId());

        self::assertResponseStatusCodeSame(403);
    }
}