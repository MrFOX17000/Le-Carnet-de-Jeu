<?php

namespace App\Tests\API;

use App\Domain\Entry\EntryType;
use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\Entry;
use App\Entity\EntryMatch;
use App\Entity\EntryScore;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class SessionApiReadTest extends DbWebTestCase
{
    public function testMemberCanSeeGroupSessions(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-session-read-owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Session Read Group');
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

        $session1 = new Session();
        $session1->setActivity($activity);
        $session1->setGroup($group);
        $session1->setCreatedBy($owner);
        $session1->setPlayedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));
        $session1->setTitle('Soirée ranked');
        $group->addSession($session1);
        $activity->addSession($session1);

        $session2 = new Session();
        $session2->setActivity($activity);
        $session2->setGroup($group);
        $session2->setCreatedBy($owner);
        $session2->setPlayedAt(new \DateTimeImmutable('2026-03-05T19:00:00+00:00'));
        $group->addSession($session2);
        $activity->addSession($session2);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->persist($session1);
        $em->persist($session2);
        $em->flush();

        $client->loginUser($owner);
        $client->request('GET', '/api/groups/' . $group->getId() . '/sessions');

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload['data'] ?? null);
        self::assertCount(2, $payload['data']);

        // Vérifie que la session la plus récente est en premier (playedAt DESC)
        self::assertSame('Soirée ranked', $payload['data'][0]['title'] ?? null);
        self::assertSame($session1->getId(), $payload['data'][0]['id'] ?? null);
        self::assertSame($activity->getName(), $payload['data'][0]['activityName'] ?? null);
    }

    public function testNonMemberGets403OnListSessions(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-session-read-owner-2@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $otherUser = new User();
        $otherUser->setEmail('api-session-read-other@test.local');
        $otherUser->setPassword('dummy');
        $otherUser->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Private Session Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);

        $em->persist($owner);
        $em->persist($otherUser);
        $em->persist($group);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($otherUser);
        $client->request('GET', '/api/groups/' . $group->getId() . '/sessions');

        self::assertResponseStatusCodeSame(403);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('forbidden', $payload['error']['code'] ?? null);
    }

    public function testAnonymousGets401OnListSessions(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/groups/1/sessions');

        self::assertResponseStatusCodeSame(401);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('unauthorized', $payload['error']['code'] ?? null);
    }

    public function testSessionsFromOtherGroupsNotVisible(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner1 = new User();
        $owner1->setEmail('api-session-isolation-owner1@test.local');
        $owner1->setPassword('dummy');
        $owner1->setCreatedAt(new \DateTimeImmutable());

        $owner2 = new User();
        $owner2->setEmail('api-session-isolation-owner2@test.local');
        $owner2->setPassword('dummy');
        $owner2->setCreatedAt(new \DateTimeImmutable());

        $group1 = new GameGroup();
        $group1->setName('Session Group 1');
        $group1->setCreatedBy($owner1);
        $group1->setCreatedAt(new \DateTimeImmutable());

        $membership1 = new GroupMember(GroupRole::OWNER);
        $group1->addGroupMember($membership1);
        $owner1->addGroupMember($membership1);

        $group2 = new GameGroup();
        $group2->setName('Session Group 2');
        $group2->setCreatedBy($owner2);
        $group2->setCreatedAt(new \DateTimeImmutable());

        $membership2 = new GroupMember(GroupRole::OWNER);
        $group2->addGroupMember($membership2);
        $owner2->addGroupMember($membership2);

        $activity1 = new Activity();
        $activity1->setName('Activity 1');
        $activity1->setGroup($group1);
        $activity1->setCreatedBy($owner1);
        $activity1->setCreatedAt(new \DateTimeImmutable());
        $group1->addActivity($activity1);

        $activity2 = new Activity();
        $activity2->setName('Activity 2');
        $activity2->setGroup($group2);
        $activity2->setCreatedBy($owner2);
        $activity2->setCreatedAt(new \DateTimeImmutable());
        $group2->addActivity($activity2);

        $session1 = new Session();
        $session1->setActivity($activity1);
        $session1->setGroup($group1);
        $session1->setCreatedBy($owner1);
        $session1->setPlayedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));
        $group1->addSession($session1);
        $activity1->addSession($session1);

        $session2 = new Session();
        $session2->setActivity($activity2);
        $session2->setGroup($group2);
        $session2->setCreatedBy($owner2);
        $session2->setPlayedAt(new \DateTimeImmutable('2026-03-06T20:00:00+00:00'));
        $group2->addSession($session2);
        $activity2->addSession($session2);

        $em->persist($owner1);
        $em->persist($owner2);
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($membership1);
        $em->persist($membership2);
        $em->persist($activity1);
        $em->persist($activity2);
        $em->persist($session1);
        $em->persist($session2);
        $em->flush();

        $client->loginUser($owner1);
        $client->request('GET', '/api/groups/' . $group1->getId() . '/sessions');

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload['data'] ?? null);
        self::assertCount(1, $payload['data']);
        self::assertSame($session1->getId(), $payload['data'][0]['id'] ?? null);
    }

    public function testMemberCanSeeSessionDetails(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-session-detail-owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Session Detail Group');
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
        $session->setTitle('Soirée ranked');
        $group->addSession($session);
        $activity->addSession($session);

        // Entry score_simple
        $entryScore = new Entry(EntryType::SCORE_SIMPLE);
        $entryScore->setSession($session);
        $entryScore->setGroup($group);
        $entryScore->setLabel('Manche 1');
        $entryScore->setCreatedBy($owner);

        $score1 = new EntryScore();
        $score1->setParticipantName('Mathias');
        $score1->setScore(42);
        $entryScore->addScore($score1);

        $score2 = new EntryScore();
        $score2->setParticipantName('Lucas');
        $score2->setScore(38);
        $entryScore->addScore($score2);

        $session->addEntry($entryScore);

        // Entry match
        $entryMatch = new Entry(EntryType::MATCH);
        $entryMatch->setSession($session);
        $entryMatch->setGroup($group);
        $entryMatch->setLabel('Finale');
        $entryMatch->setCreatedBy($owner);

        $match = new EntryMatch();
        $match->setHomeName('Team Blue');
        $match->setAwayName('Team Orange');
        $match->setHomeScore(3);
        $match->setAwayScore(1);
        $entryMatch->setEntryMatch($match);

        $session->addEntry($entryMatch);

        $em->persist($owner);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->persist($session);
        $em->persist($entryScore);
        $em->persist($entryMatch);
        $em->flush();

        $client->loginUser($owner);
        $client->request('GET', '/api/groups/' . $group->getId() . '/sessions/' . $session->getId());

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame($session->getId(), $payload['data']['id'] ?? null);
        self::assertSame('Soirée ranked', $payload['data']['title'] ?? null);
        self::assertSame($activity->getName(), $payload['data']['activityName'] ?? null);
        self::assertIsArray($payload['data']['entries'] ?? null);
        self::assertCount(2, $payload['data']['entries']);

        // Vérifie l'entry score_simple
        $scoreEntry = null;
        foreach ($payload['data']['entries'] as $entry) {
            if ($entry['type'] === 'score_simple') {
                $scoreEntry = $entry;
                break;
            }
        }
        self::assertNotNull($scoreEntry);
        self::assertSame('Manche 1', $scoreEntry['label']);
        self::assertCount(2, $scoreEntry['details']['scores']);

        // Vérifie l'entry match
        $matchEntry = null;
        foreach ($payload['data']['entries'] as $entry) {
            if ($entry['type'] === 'match') {
                $matchEntry = $entry;
                break;
            }
        }
        self::assertNotNull($matchEntry);
        self::assertSame('Finale', $matchEntry['label']);
        self::assertSame('Team Blue', $matchEntry['details']['homeName']);
        self::assertSame('Team Orange', $matchEntry['details']['awayName']);
        self::assertSame(3, $matchEntry['details']['homeScore']);
        self::assertSame(1, $matchEntry['details']['awayScore']);
    }

    public function testNonMemberGets403OnSessionDetails(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-session-detail-owner-2@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $otherUser = new User();
        $otherUser->setEmail('api-session-detail-other@test.local');
        $otherUser->setPassword('dummy');
        $otherUser->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Private Session Detail Group');
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
        $em->persist($otherUser);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->persist($session);
        $em->flush();

        $client->loginUser($otherUser);
        $client->request('GET', '/api/groups/' . $group->getId() . '/sessions/' . $session->getId());

        self::assertResponseStatusCodeSame(403);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('forbidden', $payload['error']['code'] ?? null);
    }

    public function testSessionNotFoundGets404(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('api-session-not-found@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Session Not Found Group');
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
        $client->request('GET', '/api/groups/' . $group->getId() . '/sessions/999999');

        self::assertResponseStatusCodeSame(404);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('not_found', $payload['error']['code'] ?? null);
    }
}
