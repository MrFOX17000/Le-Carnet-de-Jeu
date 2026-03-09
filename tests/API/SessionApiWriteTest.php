<?php

namespace App\Tests\API;

use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class SessionApiWriteTest extends DbWebTestCase
{
    public function testOwnerCreatesSessionViaApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-session-owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Session Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);

        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $activity->setCreatedAt(new \DateTimeImmutable());
        $group->addActivity($activity);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->flush();

        $playedAt = new \DateTimeImmutable('2026-03-06T20:00:00+00:00');
        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/sessions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'activityId' => $activity->getId(),
                'playedAt' => $playedAt->format(\DateTimeInterface::ATOM),
                'title' => 'Soirée ranked',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame($group->getId(), $payload['data']['groupId'] ?? null);
        self::assertIsInt($payload['data']['id'] ?? null);

        $session = $em->getRepository(Session::class)->find($payload['data']['id']);
        self::assertNotNull($session);
        self::assertSame('Soirée ranked', $session->getTitle());
        self::assertSame($activity->getId(), $session->getActivity()?->getId());
        self::assertSame($group->getId(), $session->getGroup()?->getId());
    }

    public function testAnonymousGets401OnCreateSessionApi(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/groups/1/sessions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'activityId' => 1,
                'playedAt' => '2026-03-06T20:00:00+00:00',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(401);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('unauthorized', $payload['error']['code'] ?? null);
    }

    public function testMemberGets403OnCreateSessionApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-session-owner-2@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $memberUser = new User();
        $memberUser->setEmail('api-session-member@test.local');
        $memberUser->setPassword('dummy');
        $memberUser->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Forbidden Session Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($memberMembership);
        $memberUser->addGroupMember($memberMembership);

        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $activity->setCreatedAt(new \DateTimeImmutable());
        $group->addActivity($activity);

        $em->persist($owner);
        $em->persist($memberUser);
        $em->persist($group);
        $em->persist($ownerMembership);
        $em->persist($memberMembership);
        $em->persist($activity);
        $em->flush();

        $client->loginUser($memberUser);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/sessions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'activityId' => $activity->getId(),
                'playedAt' => '2026-03-06T20:00:00+00:00',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(403);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('forbidden', $payload['error']['code'] ?? null);
    }

    public function testMissingActivityIdGets422OnCreateSessionApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-session-validation-1@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Session Validation Group 1');
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
            '/api/groups/' . $group->getId() . '/sessions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'playedAt' => '2026-03-06T20:00:00+00:00',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('activity_id_required', $payload['error']['code'] ?? null);
    }

    public function testMissingPlayedAtGets422OnCreateSessionApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-session-validation-2@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Session Validation Group 2');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);

        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $activity->setCreatedAt(new \DateTimeImmutable());
        $group->addActivity($activity);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/sessions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'activityId' => $activity->getId(),
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('played_at_required', $payload['error']['code'] ?? null);
    }

    public function testInvalidJsonGets400OnCreateSessionApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-session-invalid-json@test.local');
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
            '/api/groups/' . $group->getId() . '/sessions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"activityId":1,"playedAt":"broken"'
        );

        self::assertResponseStatusCodeSame(400);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('invalid_json', $payload['error']['code'] ?? null);
    }

    public function testUnknownGroupGets404OnCreateSessionApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-session-not-found@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $em->persist($owner);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/999999/sessions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'activityId' => 1,
                'playedAt' => '2026-03-06T20:00:00+00:00',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(404);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('not_found', $payload['error']['code'] ?? null);
    }

    public function testActivityFromDifferentGroupGets422OnCreateSessionApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Owner 1 with Group 1
        $owner1 = new User();
        $owner1->setEmail('api-session-owner-group1@test.local');
        $owner1->setPassword('dummy');
        $owner1->setCreatedAt(new \DateTimeImmutable());

        $group1 = new GameGroup();
        $group1->setName('Session Group 1');
        $group1->setCreatedBy($owner1);
        $group1->setCreatedAt(new \DateTimeImmutable());

        $membership1 = new GroupMember(GroupRole::OWNER);
        $group1->addGroupMember($membership1);
        $owner1->addGroupMember($membership1);

        // Group 2 (different)
        $owner2 = new User();
        $owner2->setEmail('api-session-owner-group2@test.local');
        $owner2->setPassword('dummy');
        $owner2->setCreatedAt(new \DateTimeImmutable());

        $group2 = new GameGroup();
        $group2->setName('Session Group 2');
        $group2->setCreatedBy($owner2);
        $group2->setCreatedAt(new \DateTimeImmutable());

        $membership2 = new GroupMember(GroupRole::OWNER);
        $group2->addGroupMember($membership2);
        $owner2->addGroupMember($membership2);

        // Activity in Group 2... but we'll try to create a session in Group 1 with this activity
        $activity = new Activity();
        $activity->setName('Rocket League Group 2');
        $activity->setGroup($group2);
        $activity->setCreatedBy($owner2);
        $activity->setCreatedAt(new \DateTimeImmutable());
        $group2->addActivity($activity);

        $em->persist($owner1);
        $em->persist($owner2);
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($membership1);
        $em->persist($membership2);
        $em->persist($activity);
        $em->flush();

        // Owner 1 tries to create a session in Group 1 using an activity from Group 2
        $client->loginUser($owner1);
        $client->request(
            'POST',
            '/api/groups/' . $group1->getId() . '/sessions',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'activityId' => $activity->getId(),
                'playedAt' => '2026-03-06T20:00:00+00:00',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('activity_not_in_group', $payload['error']['code'] ?? null);
    }
}
