<?php

namespace App\Tests\UI\Session;

use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Domain\Group\GroupRole;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class EnableSessionShareControllerTest extends DbWebTestCase
{
    public function testOwnerCanEnableSessionShare(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Créer un utilisateur propriétaire
        $owner = new User();
        $owner->setEmail('owner-share-allowed@test.local');
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
        $group = $em->find(GameGroup::class, $group->getId());

        // Créer une activité
        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);

        $em->persist($activity);
        $em->flush();

        // Créer une session
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06 20:00:00'));

        $em->persist($session);
        $em->flush();

        // Se connecter en tant qu'owner
        $client->loginUser($owner);

        // Accéder au contrôleur d'activation du partage
        $client->request(
            'POST',
            sprintf('/groups/%d/sessions/%d/share', $group->getId(), $session->getId())
        );

        // Vérifier la redirection (302 ou 303)
        self::assertTrue(
            $client->getResponse()->isRedirect(),
            sprintf('Expected redirect, got %d', $client->getResponse()->getStatusCode())
        );

        // Vérifier que le token a été généré
        $em->clear();

        $updatedSession = $em->find(Session::class, $session->getId());
        self::assertNotNull($updatedSession);
        self::assertNotNull($updatedSession->getUnlistedToken());
    }

    public function testNonMemberCannotEnableSessionShare(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Créer deux utilisateurs
        $owner = new User();
        $owner->setEmail('owner-share-forbidden@test.local');
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

        // Ajouter l'owner comme membre du groupe avec rôle OWNER
        $membership = new GroupMember(GroupRole::OWNER);
        $membership->setUser($owner);
        $membership->setGroup($group);

        $em->persist($membership);
        $em->flush();

        // Créer une activité
        $activity = new Activity();
        $activity->setName('Rocket League');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);

        $em->persist($activity);
        $em->flush();

        // Créer une session
        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($owner);
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-06 20:00:00'));

        $em->persist($session);
        $em->flush();

        // Se connecter en tant que non-membre
        $client->loginUser($nonMember);

        // Essayer d'accéder au contrôleur d'activation du partage
        $client->request(
            'POST',
            sprintf('/groups/%d/sessions/%d/share', $group->getId(), $session->getId())
        );

        // Vérifier que l'accès est refusé (403)
        self::assertResponseStatusCodeSame(403);
    }
}
