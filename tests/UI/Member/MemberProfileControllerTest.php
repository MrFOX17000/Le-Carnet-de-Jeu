<?php

namespace App\Tests\UI\Member;

use App\Domain\Activity\ContextMode;
use App\Domain\Entry\EntryType;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Activity;
use App\Entity\Entry;
use App\Entity\EntryMatch;
use App\Entity\EntryScore;
use App\Entity\Session;
use App\Entity\User;
use App\Domain\Group\GroupRole;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class MemberProfileControllerTest extends DbWebTestCase
{
    public function testMemberCanViewOwnProfile(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Créer deux utilisateurs
        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('hashed_password');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $owner->setIsVerified(true);

        $member = new User();
        $member->setEmail('member@test.local');
        $member->setPassword('hashed_password');
        $member->setCreatedAt(new \DateTimeImmutable());
        $member->setIsVerified(true);

        $em->persist($owner);
        $em->persist($member);
        $em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        // Ajouter les deux utilisateurs au groupe
        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($memberMembership);
        $member->addGroupMember($memberMembership);

        $em->persist($group);
        $em->persist($ownerMembership);
        $em->persist($memberMembership);
        $em->flush();
        $em->clear();

        // Recharger les entités après clear
        $member = $em->find(User::class, $member->getId());
        $group = $em->find(GameGroup::class, $group->getId());

        // Se connecter en tant que member
        $client->loginUser($member);

        // Accéder au profil du member
        $client->request('GET', sprintf('/groups/%d/members/%d', $group->getId(), $member->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($member->getEmail(), $content);
        self::assertStringContainsString('Statistiques', $content);
        self::assertStringContainsString('Activités maîtrisées', $content);
    }

    public function testNonMemberCannotViewMemberProfile(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Créer trois utilisateurs
        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('hashed_password');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $owner->setIsVerified(true);

        $member = new User();
        $member->setEmail('member@test.local');
        $member->setPassword('hashed_password');
        $member->setCreatedAt(new \DateTimeImmutable());
        $member->setIsVerified(true);

        $outsider = new User();
        $outsider->setEmail('outsider@test.local');
        $outsider->setPassword('hashed_password');
        $outsider->setCreatedAt(new \DateTimeImmutable());
        $outsider->setIsVerified(true);

        $em->persist($owner);
        $em->persist($member);
        $em->persist($outsider);
        $em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        // Ajouter seulement owner et member au groupe
        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($memberMembership);
        $member->addGroupMember($memberMembership);

        $em->persist($group);
        $em->persist($ownerMembership);
        $em->persist($memberMembership);
        $em->flush();

        // Se connecter en tant que outsider
        $client->loginUser($outsider);

        // Essayer d'accéder au profil du member
        $client->request('GET', sprintf('/groups/%d/members/%d', $group->getId(), $member->getId()));

        // Vérifier que l'accès est refusé (403)
        self::assertResponseStatusCodeSame(403);
    }

    public function testMemberProfileDisplaysCompetitiveStatsAndTimeline(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setDisplayName('Boss');
        $owner->setPassword('hashed_password');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $owner->setIsVerified(true);

        $member = new User();
        $member->setEmail('member@test.local');
        $member->setDisplayName('Prodige');
        $member->setPassword('hashed_password');
        $member->setCreatedAt(new \DateTimeImmutable());
        $member->setIsVerified(true);

        $em->persist($owner);
        $em->persist($member);
        $em->flush();

        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($memberMembership);
        $member->addGroupMember($memberMembership);

        $rankingActivity = new Activity();
        $rankingActivity->setName('Skyjo');
        $rankingActivity->setContextMode(ContextMode::RANKING);
        $rankingActivity->setGroup($group);
        $rankingActivity->setCreatedBy($owner);
        $group->addActivity($rankingActivity);

        $matchActivity = new Activity();
        $matchActivity->setName('Rocket League');
        $matchActivity->setContextMode(ContextMode::DUEL);
        $matchActivity->setGroup($group);
        $matchActivity->setCreatedBy($owner);
        $group->addActivity($matchActivity);

        $sessionOne = new Session();
        $sessionOne->setGroup($group);
        $sessionOne->setActivity($rankingActivity);
        $sessionOne->setCreatedBy($owner);
        $sessionOne->setTitle('Soirée Skyjo');
        $sessionOne->setPlayedAt(new \DateTimeImmutable('2026-03-08 20:00:00'));
        $group->addSession($sessionOne);
        $rankingActivity->addSession($sessionOne);

        $scoreEntry = new Entry(EntryType::SCORE_SIMPLE);
        $scoreEntry->setGroup($group);
        $scoreEntry->setSession($sessionOne);
        $scoreEntry->setCreatedBy($owner);
        $scoreEntry->setLabel('Manche finale');

        $memberScore = new EntryScore();
        $memberScore->setEntry($scoreEntry);
        $memberScore->setUser($member);
        $memberScore->setParticipantName('Prodige');
        $memberScore->setScore(87);
        $scoreEntry->addScore($memberScore);

        $sessionTwo = new Session();
        $sessionTwo->setGroup($group);
        $sessionTwo->setActivity($matchActivity);
        $sessionTwo->setCreatedBy($owner);
        $sessionTwo->setTitle('Scrim RL');
        $sessionTwo->setPlayedAt(new \DateTimeImmutable('2026-03-10 21:00:00'));
        $group->addSession($sessionTwo);
        $matchActivity->addSession($sessionTwo);

        $matchEntry = new Entry(EntryType::MATCH);
        $matchEntry->setGroup($group);
        $matchEntry->setSession($sessionTwo);
        $matchEntry->setCreatedBy($owner);
        $matchEntry->setLabel('BO1');

        $match = new EntryMatch();
        $match->setEntry($matchEntry);
        $match->setHomeName('Prodige');
        $match->setAwayName('Adversaire');
        $match->setHomeUser($member);
        $match->setHomeScore(4);
        $match->setAwayScore(2);

        $em->persist($group);
        $em->persist($ownerMembership);
        $em->persist($memberMembership);
        $em->persist($rankingActivity);
        $em->persist($matchActivity);
        $em->persist($sessionOne);
        $em->persist($scoreEntry);
        $em->persist($memberScore);
        $em->persist($sessionTwo);
        $em->persist($matchEntry);
        $em->flush();

        $client->loginUser($owner);
        $client->request('GET', sprintf('/groups/%d/members/%d', $group->getId(), $member->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Prodige', $content);
        self::assertStringContainsString('Meilleure perf', $content);
        self::assertStringContainsString('87', $content);
        self::assertStringContainsString('Activités maîtrisées', $content);
        self::assertStringContainsString('Skyjo', $content);
        self::assertStringContainsString('Rocket League', $content);
        self::assertStringContainsString('MMR', $content);
        self::assertStringContainsString('1020 (+20)', $content);
        self::assertStringContainsString('Records détenus', $content);
        self::assertStringContainsString('Record de score', $content);
        self::assertStringContainsString('Leader MMR', $content);
        self::assertStringContainsString('Top 1 de la saison', $content);
        self::assertStringContainsString('Forme récente', $content);
        self::assertStringContainsString('Scrim RL', $content);
        self::assertStringContainsString('Victoire sur Rocket League', $content);
    }

    public function testMemberProfileCanFilterBySeason(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('owner-filter@test.local');
        $owner->setPassword('hashed_password');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $owner->setIsVerified(true);

        $member = new User();
        $member->setEmail('member-filter@test.local');
        $member->setPassword('hashed_password');
        $member->setCreatedAt(new \DateTimeImmutable());
        $member->setIsVerified(true);

        $group = new GameGroup();
        $group->setName('Season Member Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($ownerMembership);
        $owner->addGroupMember($ownerMembership);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($memberMembership);
        $member->addGroupMember($memberMembership);

        $activity = new Activity();
        $activity->setName('Saisons Duel');
        $activity->setContextMode(ContextMode::DUEL);
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $group->addActivity($activity);

        $sessionMarch = new Session();
        $sessionMarch->setGroup($group);
        $sessionMarch->setActivity($activity);
        $sessionMarch->setCreatedBy($owner);
        $sessionMarch->setTitle('Mars Match');
        $sessionMarch->setPlayedAt(new \DateTimeImmutable('2026-03-10 21:00:00'));
        $group->addSession($sessionMarch);
        $activity->addSession($sessionMarch);

        $entryMarch = new Entry(EntryType::MATCH);
        $entryMarch->setGroup($group);
        $entryMarch->setSession($sessionMarch);
        $entryMarch->setCreatedBy($owner);
        $entryMarch->setLabel('Mars BO1');

        $matchMarch = new EntryMatch();
        $matchMarch->setEntry($entryMarch);
        $matchMarch->setHomeName('member-filter');
        $matchMarch->setAwayName('externes');
        $matchMarch->setHomeUser($member);
        $matchMarch->setHomeScore(3);
        $matchMarch->setAwayScore(1);

        $sessionFebruary = new Session();
        $sessionFebruary->setGroup($group);
        $sessionFebruary->setActivity($activity);
        $sessionFebruary->setCreatedBy($owner);
        $sessionFebruary->setTitle('Février Match');
        $sessionFebruary->setPlayedAt(new \DateTimeImmutable('2026-02-02 21:00:00'));
        $group->addSession($sessionFebruary);
        $activity->addSession($sessionFebruary);

        $entryFebruary = new Entry(EntryType::MATCH);
        $entryFebruary->setGroup($group);
        $entryFebruary->setSession($sessionFebruary);
        $entryFebruary->setCreatedBy($owner);
        $entryFebruary->setLabel('Février BO1');

        $matchFebruary = new EntryMatch();
        $matchFebruary->setEntry($entryFebruary);
        $matchFebruary->setHomeName('member-filter');
        $matchFebruary->setAwayName('externes');
        $matchFebruary->setHomeUser($member);
        $matchFebruary->setHomeScore(2);
        $matchFebruary->setAwayScore(3);

        foreach ([$owner, $member, $group, $ownerMembership, $memberMembership, $activity, $sessionMarch, $entryMarch, $sessionFebruary, $entryFebruary] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client->loginUser($owner);
        $client->request('GET', sprintf('/groups/%d/members/%d?season=2026-03', $group->getId(), $member->getId()));

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Hall of fame saisonnier', $content);
        self::assertStringContainsString('Records détenus', $content);
        self::assertStringContainsString('Mars Match', $content);
        self::assertStringNotContainsString('Février Match', $content);
    }
}
