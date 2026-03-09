<?php

namespace App\Tests\API;

use App\Domain\Group\GroupRole;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class GroupApiReadTest extends DbWebTestCase
{
    public function testAuthenticatedUserSeesOnlyOwnGroupsInApiList(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('api-user@test.local');
        $user->setPassword('dummy');
        $user->setCreatedAt(new \DateTimeImmutable());

        $otherUser = new User();
        $otherUser->setEmail('api-other@test.local');
        $otherUser->setPassword('dummy');
        $otherUser->setCreatedAt(new \DateTimeImmutable());

        $myGroup = new GameGroup();
        $myGroup->setName('My API Group');
        $myGroup->setCreatedBy($user);
        $myGroup->setCreatedAt(new \DateTimeImmutable('2026-03-01T20:00:00+00:00'));

        $otherGroup = new GameGroup();
        $otherGroup->setName('Other API Group');
        $otherGroup->setCreatedBy($otherUser);
        $otherGroup->setCreatedAt(new \DateTimeImmutable('2026-03-02T20:00:00+00:00'));

        $myMembership = new GroupMember(GroupRole::OWNER);
        $myGroup->addGroupMember($myMembership);
        $user->addGroupMember($myMembership);

        $otherMembership = new GroupMember(GroupRole::OWNER);
        $otherGroup->addGroupMember($otherMembership);
        $otherUser->addGroupMember($otherMembership);

        $em->persist($user);
        $em->persist($otherUser);
        $em->persist($myGroup);
        $em->persist($otherGroup);
        $em->persist($myMembership);
        $em->persist($otherMembership);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/api/groups');

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('data', $payload);
        self::assertCount(1, $payload['data']);
        self::assertSame('My API Group', $payload['data'][0]['name']);
        self::assertSame('OWNER', $payload['data'][0]['role']);
    }

    public function testAuthenticatedUserCannotViewGroupTheyDoNotBelongTo(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $intruder = new User();
        $intruder->setEmail('api-intruder@test.local');
        $intruder->setPassword('dummy');
        $intruder->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Forbidden API Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);

        $em->persist($owner);
        $em->persist($intruder);
        $em->persist($group);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($intruder);
        $client->request('GET', '/api/groups/' . $group->getId());

        self::assertResponseStatusCodeSame(403);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('forbidden', $payload['error']['code'] ?? null);
    }

    public function testAnonymousUserIsRejectedOnApiList(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/groups');

        self::assertResponseStatusCodeSame(401);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('unauthorized', $payload['error']['code'] ?? null);
    }

    public function testAuthenticatedUserCanViewOwnGroupDetails(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('api-show@test.local');
        $user->setPassword('dummy');
        $user->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Detail API Group');
        $group->setCreatedBy($user);
        $group->setCreatedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $user->addGroupMember($membership);

        $em->persist($user);
        $em->persist($group);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/api/groups/' . $group->getId());

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Detail API Group', $payload['data']['name'] ?? null);
        self::assertSame('OWNER', $payload['data']['role'] ?? null);
    }
}
