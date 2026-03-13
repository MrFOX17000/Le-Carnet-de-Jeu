<?php

namespace App\Tests\UI\Session;

use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Domain\Group\GroupRole;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class CreateSessionControllerTest extends DbWebTestCase
{
    public function testOwnerCanAccessCreateSessionForm(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Créer un utilisateur propriétaire
        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('hashed_password');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $owner->setIsVerified(true);

        $em->persist($owner);
        $em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $em->persist($group);
        $em->flush();

        // Ajouter l'owner comme membre du groupe avec rôle OWNER
        $membership = new GroupMember(GroupRole::OWNER);
        $membership->setUser($owner);
        $membership->setGroup($group);

        $em->persist($membership);
        $em->flush();
        $em->clear();

        // Recharger les entités après clear
        $owner = $em->find(User::class, $owner->getId());
        
        // Créer une activité
        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($em->find(GameGroup::class, $group->getId()));
        $activity->setCreatedBy($owner);

        $em->persist($activity);
        $em->flush();

        // Se connecter en tant qu'owner
        $client->loginUser($owner);

        // Accéder au formulaire de création de session
        $client->request('GET', sprintf('/groups/%d/sessions/create', $group->getId()));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Créer une session', $client->getResponse()->getContent());
    }

    public function testNonMemberCannotAccessCreateSessionForm(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Créer deux utilisateurs
        $owner = new User();
        $owner->setEmail('owner@test.local');
        $owner->setPassword('hashed_password');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $owner->setIsVerified(true);

        $nonMember = new User();
        $nonMember->setEmail('nonmember@test.local');
        $nonMember->setPassword('hashed_password');
        $nonMember->setCreatedAt(new \DateTimeImmutable());
        $nonMember->setIsVerified(true);

        $em->persist($owner);
        $em->persist($nonMember);
        $em->flush();

        // Créer un groupe
        $group = new GameGroup();
        $group->setName('Test Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $em->persist($group);
        $em->flush();

        // Se connecter en tant que non-member
        $client->loginUser($nonMember);

        // Essayer d'accéder au formulaire de création de session
        $client->request('GET', sprintf('/groups/%d/sessions/create', $group->getId()));

        // Devrait être refusé (403)
        self::assertResponseStatusCodeSame(403);
    }

    public function testPreferredActivityFromQueryIsPreselected(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('owner-preselect@test.local');
        $owner->setPassword('hashed_password');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $owner->setIsVerified(true);

        $em->persist($owner);
        $em->flush();

        $group = new GameGroup();
        $group->setName('Test Group Preselect');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);

        $em->persist($group);
        $em->flush();

        $membership = new GroupMember(GroupRole::OWNER);
        $membership->setUser($owner);
        $membership->setGroup($group);

        $activityA = new Activity();
        $activityA->setName('Rocket League');
        $activityA->setGroup($group);
        $activityA->setCreatedBy($owner);

        $activityB = new Activity();
        $activityB->setName('Mario Kart');
        $activityB->setGroup($group);
        $activityB->setCreatedBy($owner);

        $em->persist($membership);
        $em->persist($activityA);
        $em->persist($activityB);
        $em->flush();

        $groupId = $group->getId();
        $activityBId = $activityB->getId();
        $ownerId = $owner->getId();
        $em->clear();

        $owner = $em->find(User::class, $ownerId);

        $client->loginUser($owner);
        $crawler = $client->request('GET', sprintf('/groups/%d/sessions/create?activity=%d', $groupId, $activityBId));

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter(sprintf('option[value="%d"][selected]', $activityBId))->count());
    }
}
