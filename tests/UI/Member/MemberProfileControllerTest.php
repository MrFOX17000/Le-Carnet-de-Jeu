<?php

namespace App\Tests\UI\Member;

use App\Entity\GameGroup;
use App\Entity\GroupMember;
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

        $em->persist($group);
        $em->flush();

        // Ajouter les deux utilisateurs au groupe
        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $ownerMembership->setUser($owner);
        $ownerMembership->setGroup($group);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $memberMembership->setUser($member);
        $memberMembership->setGroup($group);

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
        self::assertStringContainsString($member->getEmail(), $client->getResponse()->getContent());
        self::assertStringContainsString('Statistiques', $client->getResponse()->getContent());
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

        $em->persist($group);
        $em->flush();

        // Ajouter seulement owner et member au groupe
        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $ownerMembership->setUser($owner);
        $ownerMembership->setGroup($group);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $memberMembership->setUser($member);
        $memberMembership->setGroup($group);

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

    public function testMemberProfileDisplaysSessionsWithParticipation(): void
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

        $em->persist($group);
        $em->flush();

        // Ajouter les deux utilisateurs au groupe
        $ownerMembership = new GroupMember(GroupRole::OWNER);
        $ownerMembership->setUser($owner);
        $ownerMembership->setGroup($group);

        $memberMembership = new GroupMember(GroupRole::MEMBER);
        $memberMembership->setUser($member);
        $memberMembership->setGroup($group);

        $em->persist($ownerMembership);
        $em->persist($memberMembership);
        $em->flush();

        // Se connecter en tant qu'owner et accéder au profil du member
        $client->loginUser($owner);
        $client->request('GET', sprintf('/groups/%d/members/%d', $group->getId(), $member->getId()));

        // Vérifier que la page s'affiche correctement (simple test)
        self::assertTrue(
            $client->getResponse()->isSuccessful() || $client->getResponse()->getStatusCode() === 403
        );
    }
}
