<?php

namespace App\Tests\Security;

use App\Domain\Group\GroupRole;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Invite;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class CreateInviteTest extends DbWebTestCase
{
    public function testOwnerCanCreateInvite(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('owner-invite@test.local');
        $owner->setPassword('dummy');

        $group = new GameGroup();
        $group->setName('Invite Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($owner);
        $client->request('POST', '/groups/' . $group->getId() . '/invites/create', [
            'email' => 'friend@test.local',
        ]);

        self::assertResponseRedirects('/groups/' . $group->getId());

        $invite = $em->getRepository(Invite::class)->findOneBy([
            'email' => 'friend@test.local',
        ]);

        self::assertNotNull($invite);
        self::assertSame('friend@test.local', $invite->getEmail());
        self::assertSame(GroupRole::MEMBER, $invite->getRole());
        self::assertSame($group->getId(), $invite->getGroup()?->getId());
        self::assertSame($owner->getId(), $invite->getCreatedBy()?->getId());
        self::assertNotSame('', $invite->getToken());
    }

    public function testMemberCannotCreateInvite(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('dummy');

        $member = new User();
        $member->setEmail('member@test.local');
        $member->setPassword('dummy');

        $group = new GameGroup();
        $group->setName('Member Forbidden Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($memberMembership);
        $member->addGroupMember($memberMembership);

        $em->persist($owner);
        $em->persist($member);
        $em->persist($group);
        $em->persist($ownerMembership);
        $em->persist($memberMembership);
        $em->flush();

        $client->loginUser($member);
        $client->request('GET', '/groups/' . $group->getId() . '/invites/create');

        self::assertResponseStatusCodeSame(403);
    }

    public function testNonMemberCannotCreateInvite(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('owner2@test.local');
        $owner->setPassword('dummy');

        $intruder = new User();
        $intruder->setEmail('intruder@test.local');
        $intruder->setPassword('dummy');

        $group = new GameGroup();
        $group->setName('Secret Invite Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $em->persist($owner);
        $em->persist($intruder);
        $em->persist($group);
        $em->persist($ownerMembership);
        $em->flush();

        $client->loginUser($intruder);
        $client->request('GET', '/groups/' . $group->getId() . '/invites/create');

        self::assertResponseStatusCodeSame(403);
    }
}