<?php

namespace App\Tests\API;

use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class ActivityApiWriteTest extends DbWebTestCase
{
    public function testOwnerCreatesActivityViaApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-activity-owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Activity Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/activities',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Rocket League'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('Rocket League', $payload['data']['name'] ?? null);
        self::assertSame($group->getId(), $payload['data']['groupId'] ?? null);
        self::assertIsInt($payload['data']['id'] ?? null);

        $activity = $em->getRepository(Activity::class)->find($payload['data']['id']);
        self::assertNotNull($activity);
        self::assertSame('Rocket League', $activity->getName());
        self::assertSame($group->getId(), $activity->getGroup()?->getId());
    }

    public function testAnonymousGets401OnCreateActivityApi(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/groups/1/activities',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Rocket League'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(401);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('unauthorized', $payload['error']['code'] ?? null);
    }

    public function testMemberGets403OnCreateActivityApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-activity-owner-2@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $memberUser = new User();
        $memberUser->setEmail('api-activity-member@test.local');
        $memberUser->setPassword('dummy');
        $memberUser->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Forbidden Activity Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($memberMembership);
        $memberUser->addGroupMember($memberMembership);

        $em->persist($owner);
        $em->persist($memberUser);
        $em->persist($group);
        $em->persist($ownerMembership);
        $em->persist($memberMembership);
        $em->flush();

        $client->loginUser($memberUser);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/activities',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Rocket League'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(403);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('forbidden', $payload['error']['code'] ?? null);
    }

    public function testMissingNameGets422OnCreateActivityApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-activity-validation@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Validation Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/activities',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('name_required', $payload['error']['code'] ?? null);
    }

    public function testInvalidJsonGets400OnCreateActivityApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-activity-invalid-json@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Invalid Json Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/activities',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"name":"broken"'
        );

        self::assertResponseStatusCodeSame(400);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('invalid_json', $payload['error']['code'] ?? null);
    }

    public function testUnknownGroupGets404OnCreateActivityApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-activity-not-found@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $em->persist($owner);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/999999/activities',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Rocket League'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(404);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('not_found', $payload['error']['code'] ?? null);
    }
}
