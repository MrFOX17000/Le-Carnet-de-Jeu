<?php

namespace App\Tests\API;

use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\Entry;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class MatchEntryApiWriteTest extends DbWebTestCase
{
    public function testOwnerCreatesMatchEntryViaApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-match-owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Match Group');
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

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));
        $group->addSession($session);
        $activity->addSession($session);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->persist($session);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/sessions/' . $session->getId() . '/entries/match',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'label' => 'Finale BO1',
                'homeName' => 'Team Blue',
                'awayName' => 'Team Orange',
                'homeScore' => 3,
                'awayScore' => 1,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('match', $payload['data']['type'] ?? null);
        self::assertSame($session->getId(), $payload['data']['sessionId'] ?? null);
        self::assertSame($group->getId(), $payload['data']['groupId'] ?? null);
        self::assertIsInt($payload['data']['id'] ?? null);

        $entry = $em->getRepository(Entry::class)->find($payload['data']['id']);
        self::assertNotNull($entry);
        self::assertSame('Finale BO1', $entry->getLabel());
    }

    public function testAnonymousGets401OnCreateMatchEntryApi(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/groups/1/sessions/1/entries/match',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'homeName' => 'Team A',
                'awayName' => 'Team B',
                'homeScore' => 1,
                'awayScore' => 0,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(401);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('unauthorized', $payload['error']['code'] ?? null);
    }

    public function testMemberGets403OnCreateMatchEntryApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-match-owner-2@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $memberUser = new User();
        $memberUser->setEmail('api-match-member@test.local');
        $memberUser->setPassword('dummy');
        $memberUser->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Forbidden Match Group');
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

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));
        $group->addSession($session);
        $activity->addSession($session);

        $em->persist($owner);
        $em->persist($memberUser);
        $em->persist($group);
        $em->persist($ownerMembership);
        $em->persist($memberMembership);
        $em->persist($activity);
        $em->persist($session);
        $em->flush();

        $client->loginUser($memberUser);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/sessions/' . $session->getId() . '/entries/match',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'homeName' => 'Team A',
                'awayName' => 'Team B',
                'homeScore' => 1,
                'awayScore' => 0,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(403);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('forbidden', $payload['error']['code'] ?? null);
    }

    public function testMissingHomeNameGets422OnCreateMatchEntryApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-match-validation-1@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Match Validation Group 1');
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

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));
        $group->addSession($session);
        $activity->addSession($session);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->persist($session);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/sessions/' . $session->getId() . '/entries/match',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'awayName' => 'Team B',
                'homeScore' => 1,
                'awayScore' => 0,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('home_name_required', $payload['error']['code'] ?? null);
    }

    public function testMissingAwayNameGets422OnCreateMatchEntryApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-match-validation-2@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Match Validation Group 2');
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

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));
        $group->addSession($session);
        $activity->addSession($session);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->persist($session);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/sessions/' . $session->getId() . '/entries/match',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'homeName' => 'Team A',
                'homeScore' => 1,
                'awayScore' => 0,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('away_name_required', $payload['error']['code'] ?? null);
    }

    public function testSameTeamNamesGets422OnCreateMatchEntryApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-match-validation-3@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Match Validation Group 3');
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

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));
        $group->addSession($session);
        $activity->addSession($session);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->persist($session);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/sessions/' . $session->getId() . '/entries/match',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'homeName' => 'Team A',
                'awayName' => 'team a',
                'homeScore' => 1,
                'awayScore' => 0,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('teams_must_be_different', $payload['error']['code'] ?? null);
    }

    public function testNegativeScoreGets422OnCreateMatchEntryApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-match-validation-4@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Match Validation Group 4');
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

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));
        $group->addSession($session);
        $activity->addSession($session);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->persist($session);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/sessions/' . $session->getId() . '/entries/match',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'homeName' => 'Team A',
                'awayName' => 'Team B',
                'homeScore' => -1,
                'awayScore' => 0,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('home_score_must_be_positive', $payload['error']['code'] ?? null);
    }

    public function testInvalidJsonGets400OnCreateMatchEntryApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-match-invalid-json@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Invalid Json Group');
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

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));
        $group->addSession($session);
        $activity->addSession($session);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->persist($session);
        $em->flush();

        $client->loginUser($owner);
        $client->request(
            'POST',
            '/api/groups/' . $group->getId() . '/sessions/' . $session->getId() . '/entries/match',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"homeName":"broken'
        );

        self::assertResponseStatusCodeSame(400);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('invalid_json', $payload['error']['code'] ?? null);
    }

    public function testSessionFromDifferentGroupGets422OnCreateMatchEntryApi(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Owner 1 with Group 1
        $owner1 = new User();
        $owner1->setEmail('api-match-owner-group1@test.local');
        $owner1->setPassword('dummy');
        $owner1->setCreatedAt(new \DateTimeImmutable());

        $group1 = new GameGroup();
        $group1->setName('Match Group 1');
        $group1->setCreatedBy($owner1);
        $group1->setCreatedAt(new \DateTimeImmutable());

        $membership1 = new GroupMember(GroupRole::OWNER);
        $group1->addGroupMember($membership1);
        $owner1->addGroupMember($membership1);

        // Group 2 (different)
        $owner2 = new User();
        $owner2->setEmail('api-match-owner-group2@test.local');
        $owner2->setPassword('dummy');
        $owner2->setCreatedAt(new \DateTimeImmutable());

        $group2 = new GameGroup();
        $group2->setName('Match Group 2');
        $group2->setCreatedBy($owner2);
        $group2->setCreatedAt(new \DateTimeImmutable());

        $membership2 = new GroupMember(GroupRole::OWNER);
        $group2->addGroupMember($membership2);
        $owner2->addGroupMember($membership2);

        // Activity in Group 2, Session in Group 2
        $activity = new Activity();
        $activity->setName('Rocket League Group 2');
        $activity->setGroup($group2);
        $activity->setCreatedBy($owner2);
        $activity->setCreatedAt(new \DateTimeImmutable());
        $group2->addActivity($activity);

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group2);
        $session->setCreatedBy($owner2);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));
        $group2->addSession($session);
        $activity->addSession($session);

        $em->persist($owner1);
        $em->persist($owner2);
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($membership1);
        $em->persist($membership2);
        $em->persist($activity);
        $em->persist($session);
        $em->flush();

        // Owner 1 tries to create a match entry in Group 1 using a session from Group 2
        $client->loginUser($owner1);
        $client->request(
            'POST',
            '/api/groups/' . $group1->getId() . '/sessions/' . $session->getId() . '/entries/match',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'homeName' => 'Team A',
                'awayName' => 'Team B',
                'homeScore' => 1,
                'awayScore' => 0,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('session_not_in_group', $payload['error']['code'] ?? null);
    }
}
