<?php

namespace App\Tests\UI\Group;

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

final class SeasonArchivesControllerTest extends DbWebTestCase
{
    private static int $fixtureCounter = 0;

    public function testMemberCanBrowseSeasonArchives(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$member, $group] = $this->createSeasonArchiveFixture($em);

        $client->loginUser($member);
        $client->request('GET', '/groups/'.$group->getId().'/seasons');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Archives des saisons', $content);
        self::assertStringContainsString('S3-2026', $content);
        self::assertStringContainsString('S2-2026', $content);
        self::assertStringContainsString('Skyjo Archives', $content);
        self::assertStringContainsString('Rocket Archives', $content);
        self::assertStringContainsString('Podium', $content);
        self::assertStringContainsString('Records', $content);
        self::assertStringContainsString('Champion', $content);
        self::assertStringContainsString('Record de score', $content);
        self::assertStringContainsString('Meilleure attaque', $content);
        self::assertStringContainsString('vs S2-2026', $content);
        self::assertStringContainsString('leader changé', $content);
        self::assertStringContainsString('Competiteur → Archiviste', $content);
        self::assertStringContainsString('+22 pts leader', $content);
    }

    public function testIntruderGetsForbiddenOnSeasonArchives(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [, $group] = $this->createSeasonArchiveFixture($em);

        $intruder = new User();
        $intruder->setEmail('intruder-archives@test.local');
        $intruder->setPassword('hashed');
        $intruder->setCreatedAt(new \DateTimeImmutable());
        $em->persist($intruder);
        $em->flush();

        $client->loginUser($intruder);
        $client->request('GET', '/groups/'.$group->getId().'/seasons');

        self::assertResponseStatusCodeSame(403);
    }

    public function testMemberCanCompareTwoChosenSeasons(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$member, $group] = $this->createSeasonArchiveFixture($em);

        $client->loginUser($member);
        $client->request('GET', '/groups/'.$group->getId().'/seasons?season=2026-03&compare=2026-02');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Comparaison libre', $content);
        self::assertStringContainsString('S3-2026 · Mars 2026 vs S2-2026 · Février 2026', $content);
        self::assertStringContainsString('Sessions: 1 → 1', $content);
        self::assertStringContainsString('Leader changé', $content);
        self::assertStringContainsString('Competiteur → Archiviste', $content);
    }

    /**
     * @return array{0: User, 1: GameGroup}
     */
    private function createSeasonArchiveFixture(EntityManagerInterface $em): array
    {
        $suffix = ++self::$fixtureCounter;

        $owner = new User();
        $owner->setEmail(sprintf('owner-archives-%d@test.local', $suffix));
        $owner->setDisplayName('Archiviste');
        $owner->setPassword('hashed');
        $owner->setCreatedAt(new \DateTimeImmutable());

        $member = new User();
        $member->setEmail(sprintf('member-archives-%d@test.local', $suffix));
        $member->setDisplayName('Competiteur');
        $member->setPassword('hashed');
        $member->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Archives Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable('2026-01-01 12:00:00'));

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($memberMembership);
        $member->addGroupMember($memberMembership);

        $rankingActivity = new Activity();
        $rankingActivity->setName('Skyjo Archives');
        $rankingActivity->setContextMode(ContextMode::RANKING);
        $rankingActivity->setGroup($group);
        $rankingActivity->setCreatedBy($owner);

        $matchActivity = new Activity();
        $matchActivity->setName('Rocket Archives');
        $matchActivity->setContextMode(ContextMode::DUEL);
        $matchActivity->setGroup($group);
        $matchActivity->setCreatedBy($owner);

        $marchRanking = new Session();
        $marchRanking->setGroup($group);
        $marchRanking->setActivity($rankingActivity);
        $marchRanking->setCreatedBy($owner);
        $marchRanking->setTitle('Mars Skyjo');
        $marchRanking->setPlayedAt(new \DateTimeImmutable('2026-03-09 20:00:00'));
        $group->addSession($marchRanking);
        $rankingActivity->addSession($marchRanking);

        $rankingEntry = new Entry(EntryType::SCORE_SIMPLE);
        $rankingEntry->setGroup($group);
        $rankingEntry->setSession($marchRanking);
        $rankingEntry->setCreatedBy($owner);

        $ownerScore = new EntryScore();
        $ownerScore->setEntry($rankingEntry);
        $ownerScore->setUser($owner);
        $ownerScore->setParticipantName('Archiviste');
        $ownerScore->setScore(92);
        $rankingEntry->addScore($ownerScore);

        $memberScore = new EntryScore();
        $memberScore->setEntry($rankingEntry);
        $memberScore->setUser($member);
        $memberScore->setParticipantName('Competiteur');
        $memberScore->setScore(88);
        $rankingEntry->addScore($memberScore);

        $marchMatch = new Session();
        $marchMatch->setGroup($group);
        $marchMatch->setActivity($matchActivity);
        $marchMatch->setCreatedBy($owner);
        $marchMatch->setTitle('Mars RL');
        $marchMatch->setPlayedAt(new \DateTimeImmutable('2026-03-11 21:00:00'));
        $group->addSession($marchMatch);
        $matchActivity->addSession($marchMatch);

        $matchEntry = new Entry(EntryType::MATCH);
        $matchEntry->setGroup($group);
        $matchEntry->setSession($marchMatch);
        $matchEntry->setCreatedBy($owner);

        $match = new EntryMatch();
        $match->setEntry($matchEntry);
        $match->setHomeUser($member);
        $match->setHomeName('Competiteur');
        $match->setAwayName('Externe');
        $match->setHomeScore(5);
        $match->setAwayScore(2);

        $februaryRanking = new Session();
        $februaryRanking->setGroup($group);
        $februaryRanking->setActivity($rankingActivity);
        $februaryRanking->setCreatedBy($owner);
        $februaryRanking->setTitle('Février Skyjo');
        $februaryRanking->setPlayedAt(new \DateTimeImmutable('2026-02-05 20:00:00'));
        $group->addSession($februaryRanking);
        $rankingActivity->addSession($februaryRanking);

        $februaryEntry = new Entry(EntryType::SCORE_SIMPLE);
        $februaryEntry->setGroup($group);
        $februaryEntry->setSession($februaryRanking);
        $februaryEntry->setCreatedBy($owner);

        $februaryScore = new EntryScore();
        $februaryScore->setEntry($februaryEntry);
        $februaryScore->setUser($member);
        $februaryScore->setParticipantName('Competiteur');
        $februaryScore->setScore(70);
        $februaryEntry->addScore($februaryScore);

        foreach ([$owner, $member, $group, $ownerMembership, $memberMembership, $rankingActivity, $matchActivity, $marchRanking, $rankingEntry, $ownerScore, $memberScore, $marchMatch, $matchEntry, $februaryRanking, $februaryEntry, $februaryScore] as $entity) {
            $em->persist($entity);
        }

        $em->flush();

        return [$member, $group];
    }
}