<?php

namespace App\Tests\Dashboard;

use App\Domain\Group\GroupRole;
use App\Domain\Entry\EntryType;
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

final class DashboardControllerTest extends DbWebTestCase
{
    public function testLoggedInUserSeesOwnGroups(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Create user and groups
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('dummy');
        $user->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('My Group');
        $group->setCreatedBy($user);
        $group->setCreatedAt(new \DateTimeImmutable());

        $member = new GroupMember(GroupRole::OWNER);
        $member->setGroup($group);
        $member->setUser($user);

        $em->persist($user);
        $em->persist($group);
        $em->persist($member);
        $em->flush();

        // Login and check dashboard
        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('My Group', $client->getResponse()->getContent());
        self::assertStringContainsString('OWNER', $client->getResponse()->getContent());
    }

    public function testDashboardDisplaysRecentSessions(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Create user and group
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('dummy');
        $user->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Game Group');
        $group->setCreatedBy($user);
        $group->setCreatedAt(new \DateTimeImmutable());

        $member = new GroupMember(GroupRole::OWNER);
        $member->setGroup($group);
        $member->setUser($user);

        // Create activity and session
        $activity = new Activity();
        $activity->setName('Chess');
        $activity->setCreatedBy($user);
        $activity->setGroup($group);
        $activity->setCreatedAt(new \DateTimeImmutable());

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setTitle('Championship Game');
        $session->setPlayedAt(new \DateTimeImmutable('2025-01-15 20:00'));
        $session->setCreatedBy($user);

        $em->persist($user);
        $em->persist($group);
        $em->persist($member);
        $em->persist($activity);
        $em->persist($session);
        $em->flush();

        // Login and check dashboard
        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('Championship Game', $content);
        self::assertStringContainsString('Game Group', $content);
        self::assertStringContainsString('Chess', $content);
    }

    public function testDashboardDoesNotDisplayOtherGroupsSessions(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Create two users
        $user1 = new User();
        $user1->setEmail('user1@example.com');
        $user1->setPassword('dummy');
        $user1->setCreatedAt(new \DateTimeImmutable());

        $user2 = new User();
        $user2->setEmail('user2@example.com');
        $user2->setPassword('dummy');
        $user2->setCreatedAt(new \DateTimeImmutable());

        // User1 group
        $group1 = new GameGroup();
        $group1->setName('User 1 Group');
        $group1->setCreatedBy($user1);
        $group1->setCreatedAt(new \DateTimeImmutable());

        $member1 = new GroupMember(GroupRole::OWNER);
        $member1->setGroup($group1);
        $member1->setUser($user1);

        // User2 group
        $group2 = new GameGroup();
        $group2->setName('User 2 Group');
        $group2->setCreatedBy($user2);
        $group2->setCreatedAt(new \DateTimeImmutable());

        $member2 = new GroupMember(GroupRole::OWNER);
        $member2->setGroup($group2);
        $member2->setUser($user2);

        // Session in User2 group
        $activity2 = new Activity();
        $activity2->setName('Poker');
        $activity2->setCreatedBy($user2);
        $activity2->setGroup($group2);
        $activity2->setCreatedAt(new \DateTimeImmutable());

        $session2 = new Session();
        $session2->setActivity($activity2);
        $session2->setGroup($group2);
        $session2->setTitle('Poker Night');
        $session2->setPlayedAt(new \DateTimeImmutable('2025-01-15 22:00'));
        $session2->setCreatedBy($user2);

        $em->persist($user1);
        $em->persist($user2);
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($member1);
        $em->persist($member2);
        $em->persist($activity2);
        $em->persist($session2);
        $em->flush();

        // User1 login and check dashboard
        $client->loginUser($user1);
        $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();

        // User1 should see their group
        self::assertStringContainsString('User 1 Group', $content);

        // User1 should NOT see User2's group sessions
        self::assertStringNotContainsString('Poker Night', $content);
        self::assertStringNotContainsString('User 2 Group', $content);
    }

    public function testUnauthenticatedUserCannotAccessDashboard(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard');

        self::assertResponseRedirects('/login');
    }

    public function testDashboardDisplaysStatsWidgets(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('stats-user@example.com');
        $user->setPassword('dummy');
        $user->setCreatedAt(new \DateTimeImmutable());

        $group = new GameGroup();
        $group->setName('Stats Group');
        $group->setCreatedBy($user);
        $group->setCreatedAt(new \DateTimeImmutable());

        $membership = new GroupMember(GroupRole::OWNER);
        $membership->setGroup($group);
        $membership->setUser($user);

        $activity = new Activity();
        $activity->setName('Carcassonne');
        $activity->setCreatedBy($user);
        $activity->setGroup($group);
        $activity->setCreatedAt(new \DateTimeImmutable());

        $sessionOne = new Session();
        $sessionOne->setActivity($activity);
        $sessionOne->setGroup($group);
        $sessionOne->setTitle('Soiree points');
        $sessionOne->setPlayedAt(new \DateTimeImmutable('now -1 day'));
        $sessionOne->setCreatedBy($user);

        $sessionTwo = new Session();
        $sessionTwo->setActivity($activity);
        $sessionTwo->setGroup($group);
        $sessionTwo->setTitle('Soiree match');
        $sessionTwo->setPlayedAt(new \DateTimeImmutable('now -2 hours'));
        $sessionTwo->setCreatedBy($user);

        $scoreEntry = new Entry(EntryType::SCORE_SIMPLE);
        $scoreEntry->setSession($sessionOne);
        $scoreEntry->setGroup($group);
        $scoreEntry->setCreatedBy($user);

        $score = new EntryScore();
        $score->setParticipantName('Alice');
        $score->setScore(12);
        $scoreEntry->addScore($score);

        $matchEntry = new Entry(EntryType::MATCH);
        $matchEntry->setSession($sessionTwo);
        $matchEntry->setGroup($group);
        $matchEntry->setCreatedBy($user);

        $match = new EntryMatch();
        $match->setEntry($matchEntry);
        $match->setHomeName('Team A');
        $match->setAwayName('Team B');
        $match->setHomeScore(3);
        $match->setAwayScore(1);

        $em->persist($user);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->persist($sessionOne);
        $em->persist($sessionTwo);
        $em->persist($scoreEntry);
        $em->persist($matchEntry);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Stats rapides', $content);
        self::assertStringContainsString('Stats membre (Stats Group)', $content);
        self::assertStringContainsString('Sessions: <strong>2</strong>', $content);
        self::assertStringContainsString('Entries: <strong>2</strong>', $content);
        self::assertStringContainsString('Points cumul', $content);
        self::assertStringContainsString('12', $content);
        self::assertStringContainsString('Victoires / D', $content);
        self::assertStringContainsString('1 / 0', $content);

        self::assertStringContainsString('Stats activit', $content);
        self::assertStringContainsString('Carcassonne', $content);
        self::assertStringContainsString('Participants fr', $content);
        self::assertStringContainsString('Alice', $content);

        self::assertStringContainsString('Dashboard enrichi', $content);
        self::assertStringContainsString('Sessions cette semaine', $content);
        self::assertStringContainsString('Groupe le plus actif', $content);
        self::assertStringContainsString('Hall of Fame de saison', $content);
        self::assertStringContainsString('Top 1', $content);
        self::assertStringContainsString('Alice', $content);
        self::assertStringContainsString('Records de l\'activité favorite', $content);
        self::assertStringContainsString('Leader cumulé', $content);
        self::assertStringContainsString('Record de score', $content);
    }
}
