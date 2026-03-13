<?php

namespace App\Tests\UI\Activity;

use App\Domain\Activity\ContextMode;
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

final class ShowActivityControllerTest extends DbWebTestCase
{
    public function testMemberCanViewActivityTrackerWithLeaderboard(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('owner-activity@test.local');
        $owner->setDisplayName('Capitaine');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $challenger = new User();
        $challenger->setEmail('challenger-activity@test.local');
        $challenger->setDisplayName('Rival');
        $challenger->setPassword('dummy');
        $challenger->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Tracker Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable('2026-03-01 12:00:00'));

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($memberMembership);
        $challenger->addGroupMember($memberMembership);

        $activity = new Activity();
        $activity->setName('Skyjo V2');
        $activity->setGroup($group);
        $activity->setContextMode(ContextMode::RANKING);
        $activity->setCreatedBy($owner);
        $activity->setCreatedAt(new \DateTimeImmutable('2026-03-01 12:05:00'));
        $group->addActivity($activity);

        $sessionOne = new Session();
        $sessionOne->setGroup($group);
        $sessionOne->setActivity($activity);
        $sessionOne->setCreatedBy($owner);
        $sessionOne->setTitle('Soirée 1');
        $sessionOne->setPlayedAt(new \DateTimeImmutable('2026-03-02 20:00:00'));
        $group->addSession($sessionOne);
        $activity->addSession($sessionOne);

        $entryOne = new Entry(EntryType::SCORE_SIMPLE);
        $entryOne->setGroup($group);
        $entryOne->setSession($sessionOne);
        $entryOne->setCreatedBy($owner);
        $entryOne->setLabel('Manche 1');

        $ownerScoreOne = new EntryScore();
        $ownerScoreOne->setEntry($entryOne);
        $ownerScoreOne->setUser($owner);
        $ownerScoreOne->setParticipantName('Capitaine');
        $ownerScoreOne->setScore(42);
        $entryOne->addScore($ownerScoreOne);

        $challengerScoreOne = new EntryScore();
        $challengerScoreOne->setEntry($entryOne);
        $challengerScoreOne->setUser($challenger);
        $challengerScoreOne->setParticipantName('Rival');
        $challengerScoreOne->setScore(30);
        $entryOne->addScore($challengerScoreOne);

        $sessionTwo = new Session();
        $sessionTwo->setGroup($group);
        $sessionTwo->setActivity($activity);
        $sessionTwo->setCreatedBy($owner);
        $sessionTwo->setTitle('Soirée 2');
        $sessionTwo->setPlayedAt(new \DateTimeImmutable('2026-03-04 20:00:00'));
        $group->addSession($sessionTwo);
        $activity->addSession($sessionTwo);

        $entryTwo = new Entry(EntryType::SCORE_SIMPLE);
        $entryTwo->setGroup($group);
        $entryTwo->setSession($sessionTwo);
        $entryTwo->setCreatedBy($owner);
        $entryTwo->setLabel('Manche 2');

        $ownerScoreTwo = new EntryScore();
        $ownerScoreTwo->setEntry($entryTwo);
        $ownerScoreTwo->setUser($owner);
        $ownerScoreTwo->setParticipantName('Capitaine');
        $ownerScoreTwo->setScore(18);
        $entryTwo->addScore($ownerScoreTwo);

        $challengerScoreTwo = new EntryScore();
        $challengerScoreTwo->setEntry($entryTwo);
        $challengerScoreTwo->setUser($challenger);
        $challengerScoreTwo->setParticipantName('Rival');
        $challengerScoreTwo->setScore(55);
        $entryTwo->addScore($challengerScoreTwo);

        foreach ([$owner, $challenger, $group, $ownerMembership, $memberMembership, $activity, $sessionOne, $entryOne, $ownerScoreOne, $challengerScoreOne, $sessionTwo, $entryTwo, $ownerScoreTwo, $challengerScoreTwo] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client->loginUser($challenger);
        $client->request('GET', sprintf('/groups/%d/activities/%d', $group->getId(), $activity->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Tracker activité', $content);
        self::assertStringContainsString('Skyjo V2', $content);
        self::assertStringContainsString('Classement cumulé', $content);
        self::assertStringContainsString('Records all-time', $content);
        self::assertStringContainsString('Leader cumulé', $content);
        self::assertStringContainsString('Record de score', $content);
        self::assertStringContainsString('Participants', $content);
        self::assertStringContainsString('Capitaine', $content);
        self::assertStringContainsString('Rival', $content);
        self::assertStringContainsString('60', $content);
        self::assertStringContainsString('85', $content);
        self::assertStringContainsString('Soirée 1', $content);
        self::assertStringContainsString('Rival mène la session avec 55 pts', $content);
    }

    public function testIntruderGets403OnActivityTracker(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('owner-forbidden@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $intruder = new User();
        $intruder->setEmail('intruder-forbidden@test.local');
        $intruder->setPassword('dummy');
        $intruder->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Forbidden Tracker Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group);
        $activity->setContextMode(ContextMode::GROUPE_VS_EXTERNE);
        $activity->setCreatedBy($owner);
        $group->addActivity($activity);

        foreach ([$owner, $intruder, $group, $ownerMembership, $activity] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client->loginUser($intruder);
        $client->request('GET', sprintf('/groups/%d/activities/%d', $group->getId(), $activity->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testMatchModeDisplaysMmrInLeaderboard(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('owner-mmr@test.local');
        $owner->setDisplayName('Alpha');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $challenger = new User();
        $challenger->setEmail('challenger-mmr@test.local');
        $challenger->setDisplayName('Bravo');
        $challenger->setPassword('dummy');
        $challenger->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('MMR Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable('2026-03-01 12:00:00'));

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($memberMembership);
        $challenger->addGroupMember($memberMembership);

        $activity = new Activity();
        $activity->setName('Duel RL');
        $activity->setGroup($group);
        $activity->setContextMode(ContextMode::DUEL);
        $activity->setCreatedBy($owner);
        $group->addActivity($activity);

        $session = new Session();
        $session->setGroup($group);
        $session->setActivity($activity);
        $session->setCreatedBy($owner);
        $session->setTitle('Match 1');
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-04 20:00:00'));
        $group->addSession($session);
        $activity->addSession($session);

        $entry = new Entry(EntryType::MATCH);
        $entry->setGroup($group);
        $entry->setSession($session);
        $entry->setCreatedBy($owner);
        $entry->setLabel('BO1');

        $match = new EntryMatch();
        $match->setEntry($entry);
        $match->setHomeName('Alpha');
        $match->setAwayName('Bravo');
        $match->setHomeUser($owner);
        $match->setAwayUser($challenger);
        $match->setHomeScore(4);
        $match->setAwayScore(1);

        foreach ([$owner, $challenger, $group, $ownerMembership, $memberMembership, $activity, $session, $entry] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client->loginUser($challenger);
        $client->request('GET', sprintf('/groups/%d/activities/%d', $group->getId(), $activity->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Classement compétition', $content);
        self::assertStringContainsString('MMR', $content);
        self::assertStringContainsString('1027', $content);
        self::assertStringContainsString('973', $content);
    }

    public function testActivityTrackerCanFilterBySeason(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('owner-season@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $member = new User();
        $member->setEmail('member-season@test.local');
        $member->setPassword('dummy');
        $member->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Season Filter Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($memberMembership);
        $member->addGroupMember($memberMembership);

        $activity = new Activity();
        $activity->setName('Season Skyjo');
        $activity->setGroup($group);
        $activity->setContextMode(ContextMode::RANKING);
        $activity->setCreatedBy($owner);
        $group->addActivity($activity);

        $sessionMarch = new Session();
        $sessionMarch->setGroup($group);
        $sessionMarch->setActivity($activity);
        $sessionMarch->setCreatedBy($owner);
        $sessionMarch->setTitle('Mars Session');
        $sessionMarch->setPlayedAt(new \DateTimeImmutable('2026-03-05 20:00:00'));
        $group->addSession($sessionMarch);
        $activity->addSession($sessionMarch);

        $entryMarch = new Entry(EntryType::SCORE_SIMPLE);
        $entryMarch->setGroup($group);
        $entryMarch->setSession($sessionMarch);
        $entryMarch->setCreatedBy($owner);

        $scoreMarch = new EntryScore();
        $scoreMarch->setEntry($entryMarch);
        $scoreMarch->setUser($member);
        $scoreMarch->setParticipantName('member-season');
        $scoreMarch->setScore(66);
        $entryMarch->addScore($scoreMarch);

        $sessionFebruary = new Session();
        $sessionFebruary->setGroup($group);
        $sessionFebruary->setActivity($activity);
        $sessionFebruary->setCreatedBy($owner);
        $sessionFebruary->setTitle('Février Session');
        $sessionFebruary->setPlayedAt(new \DateTimeImmutable('2026-02-10 20:00:00'));
        $group->addSession($sessionFebruary);
        $activity->addSession($sessionFebruary);

        $entryFebruary = new Entry(EntryType::SCORE_SIMPLE);
        $entryFebruary->setGroup($group);
        $entryFebruary->setSession($sessionFebruary);
        $entryFebruary->setCreatedBy($owner);

        $scoreFebruary = new EntryScore();
        $scoreFebruary->setEntry($entryFebruary);
        $scoreFebruary->setUser($member);
        $scoreFebruary->setParticipantName('member-season');
        $scoreFebruary->setScore(40);
        $entryFebruary->addScore($scoreFebruary);

        foreach ([$owner, $member, $group, $ownerMembership, $memberMembership, $activity, $sessionMarch, $entryMarch, $scoreMarch, $sessionFebruary, $entryFebruary, $scoreFebruary] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client->loginUser($member);
        $client->request('GET', sprintf('/groups/%d/activities/%d?season=2026-03', $group->getId(), $activity->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Mars Session', $content);
        self::assertStringNotContainsString('Février Session', $content);
        self::assertStringContainsString('Records de saison', $content);
        self::assertStringContainsString('Record de score', $content);
        self::assertStringContainsString('Résumé de saison', $content);
        self::assertStringContainsString('S3-2026', $content);
    }
}