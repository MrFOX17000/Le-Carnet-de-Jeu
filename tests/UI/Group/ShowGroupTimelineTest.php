<?php

namespace App\Tests\UI\Group;

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

final class ShowGroupTimelineTest extends DbWebTestCase
{
    public function testGroupPageShowsSessionTimelineWithEntries(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $session] = $this->createGroupSessionAndEntries($em);

        $client->loginUser($owner);
        $client->request('GET', '/groups/'.$group->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Timeline des sessions');
        self::assertStringContainsString('Timeline Activity', $client->getResponse()->getContent());
        self::assertStringContainsString('Entries: 2', $client->getResponse()->getContent());
        self::assertSelectorExists('a[href="/groups/'.$group->getId().'/sessions/'.$session->getId().'"]');
    }

    /**
     * @return array{0: User, 1: GameGroup, 2: Session}
     */
    private function createGroupSessionAndEntries(EntityManagerInterface $em): array
    {
        $owner = new User();
        $owner->setEmail('timeline-owner@test.com');
        $owner->setPassword('hashed');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);

        $group = new GameGroup();
        $group->setName('Timeline Group');
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());
        $em->persist($group);

        $membership = new GroupMember(GroupRole::OWNER);
        $membership->setGroup($group);
        $membership->setUser($owner);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);
        $em->persist($membership);

        $activity = new Activity();
        $activity->setName('Timeline Activity');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $em->persist($activity);

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06 20:00:00'));
        $session->setCreatedBy($owner);
        $group->addSession($session);
        $activity->addSession($session);
        $em->persist($session);

        $scoreEntry = new Entry(EntryType::SCORE_SIMPLE);
        $scoreEntry->setSession($session);
        $scoreEntry->setGroup($group);
        $scoreEntry->setCreatedBy($owner);
        $scoreEntry->setLabel('Score manche');
        $session->addEntry($scoreEntry);

        $score = new EntryScore();
        $score->setParticipantName('Alice');
        $score->setScore(12.0);
        $scoreEntry->addScore($score);
        $em->persist($scoreEntry);

        $matchEntry = new Entry(EntryType::MATCH);
        $matchEntry->setSession($session);
        $matchEntry->setGroup($group);
        $matchEntry->setCreatedBy($owner);
        $matchEntry->setLabel('Finale');
        $session->addEntry($matchEntry);

        $match = new EntryMatch();
        $match->setHomeName('Lions');
        $match->setAwayName('Tigers');
        $match->setHomeScore(3);
        $match->setAwayScore(2);
        $match->setEntry($matchEntry);

        $em->persist($matchEntry);
        $em->flush();

        return [$owner, $group, $session];
    }
}
